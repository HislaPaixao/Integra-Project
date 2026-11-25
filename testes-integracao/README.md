# Teste de integração — Integra / NIR

Relatório do teste de integração ponta a ponta do módulo de Consultoria Ambulatorial,
executado em **24/11/2026** com a pilha real: **Angular 18 → proxy `/api` → Laravel 12 → PostgreSQL 16**.

## Resultado

| Camada testada | Como | Resultado | Evidência |
|---|---|---|---|
| Backend (unidade + feature) | PHPUnit, SQLite em memória | **44/44 testes, 134 asserções** | [`phpunit-testdox.txt`](evidencias/phpunit-testdox.txt), [`phpunit-junit.xml`](evidencias/phpunit-junit.xml) |
| API ponta a ponta (3 perfis) | [`fluxo-completo.mjs`](fluxo-completo.mjs) pelo proxy do Angular, banco PostgreSQL | **65/65 verificações**, executado 2× seguidas | [`integracao-api.txt`](evidencias/integracao-api.txt), [`integracao-api.json`](evidencias/integracao-api.json) |
| Persistência no PostgreSQL | Consulta direta com `psql` após o fluxo | Status, prioridade, agendamento e histórico gravados; sem paciente duplicado; tokens revogados | [`postgresql-conferencia.txt`](evidencias/postgresql-conferencia.txt) |
| Telas (navegador) | Roteiro de demonstração do README, manual, com as 3 personas | **Fluxo completo funcionando** | [14 capturas](evidencias/telas/) |
| Build de produção do frontend | `ng build` | Sem erros (95 kB transferidos) | — |
| Documentação Swagger | `/api/documentation` | 13 operações, todas as rotas da API | — |

## Ambiente

| Componente | Versão |
|---|---|
| PHP | 8.3.37 (NTS x64) |
| Laravel | 12.72.0 (Sanctum, l5-swagger) |
| PostgreSQL | 16.16 (autenticação `scram-sha-256`, banco `integra_nir`) |
| Node / Angular | Node 24.15.0 / Angular 18.2.14 |
| AGHU | Modo simulado (`AGHU_MOCK=true`, 6 prontuários de demonstração) |
| Sistema | Windows 11 |

Banco criado do zero com `php artisan migrate --seed`.

## O que o teste de API cobre (`fluxo-completo.mjs`)

1. **Infraestrutura**: frontend no ar, health check do Laravel, proxy `/api` do Angular, CORS para `localhost:4200`.
2. **Autenticação**: senha errada (401), e-mail inválido (422), login dos 3 perfis com token Sanctum real, `/auth/me` com nome/CRM/perfil, token inventado recusado.
3. **Pacientes (AGHU)**: os 6 prontuários de demonstração são lidos e gravados no PostgreSQL, sem duplicar paciente na segunda busca; prontuário inexistente devolve 404; sem login devolve 401.
4. **Criação da solicitação**: permissões (regulador → 403) e validações (descrição curta, clínica fora da lista, paciente inexistente → 422).
5. **Fluxo principal**: criação → busca e filtros do regulador → edição pelo dono → **Pendente** → complemento → **Triado P2** → **agendamento pelo NIR** → **Finalizado**. Inclui as tentativas proibidas em cada etapa: perfil errado (403), estado errado (422/403), data no passado, falta de local, DELETE inexistente (405) e alterações depois de finalizada.
6. **Devolução (HU09)**: motivo obrigatório e registrado, complemento, triagem P3 e **devolução do parecer pelo NIR** (finaliza sem agendamento).
7. **Encerramento**: 404 para solicitação inexistente e logout dos 3 perfis revogando o token.

Em cada etapa o script confere a resposta HTTP **e** o estado salvo (status, prioridade, agendamento, data/hora e a ordem exata do histórico).

## Roteiro nas telas (capturas em `evidencias/telas/`)

| # | Perfil | Passo | Resultado |
|---|---|---|---|
| 01 | — | Login com senha errada | Mensagem "Credenciais inválidas." |
| 02 | Médico | Prontuário `123456` → dados do AGHU preenchidos; nome e CRM do médico vêm do login | ✔ |
| 03–04 | Médico | Envio da solicitação #10 → redireciona ao Acompanhamento | ✔ "Aguarda triagem" |
| 05 | Regulador | Busca por prontuário | ✔ só as solicitações do paciente |
| 06–07 | Regulador | Resultado "Pendente" com motivo | ✔ status Pendente |
| 08–09 | Médico | Lê o motivo no histórico e envia informações complementares | ✔ volta para triagem |
| 10 | Regulador | Triado com prioridade P2 | ✔ |
| 11–13 | NIR | Lista de triadas → agendamento (data, local, justificativa) | ✔ Finalizado |
| 14 | Médico | Vê Finalizado + P2 + "Agendado para 10/12/2026 14:30" + histórico completo | ✔ |

Também verificado no navegador:
- **Guarda de rota**: o médico que abre `/regulacao` é levado de volta para `/solicitacao`.
- **Sessão persistente**: recarregar a página mantém o usuário logado.
- **Sessão revogada**: com o token apagado no banco e a tela aberta, a próxima chamada devolve 401 e o frontend volta ao login e limpa o `localStorage`.
- **Console**: nenhum erro além dos dois 401 provocados de propósito.

> Na captura 08 o motivo da pendência aparece repetido. Isso aconteceu porque a ferramenta de automação digitou o texto duas vezes no campo; não é defeito da aplicação. O teste de API confere o texto exato do motivo e passou.

## Problemas encontrados

| # | Gravidade | Onde | Problema | Sugestão |
|---|---|---|---|---|
| 1 | **Média** | `AghuService` / `PacienteController` / tela Nova Solicitação | Com o AGHU fora do ar, a API responde **404 "Paciente não encontrado no AGHU."**, a mesma resposta de prontuário inexistente (o log registra "AGHU indisponível"). O médico recebe uma mensagem errada e, como a tela exige paciente carregado para enviar, não consegue criar a solicitação. O comentário do `AghuService` (EC6) fala em "fallback manual", que não existe na tela. Verificado subindo o backend com `AGHU_BASE_URL` inacessível. | Responder 503 com "AGHU indisponível" quando a falha for de conexão/erro do AGHU, e decidir se haverá cadastro manual do paciente. |
| 2 | Baixa | `README.md` | `php artisan test` falha com *Command "test" is not defined*: o pacote `nunomaduro/collision` não está no `composer.json`. | Documentar `php vendor/bin/phpunit` ou adicionar `nunomaduro/collision` em `require-dev`. |
| 3 | Baixa (visual) | `frontend/src/styles.css` | O menu (`.menu`) e o link ativo (`.ativo`) não têm CSS: os links aparecem azuis, sublinhados e colados ("Nova SolicitaçãoAcompanhamento"), e a página atual não fica destacada. | Criar os estilos `.menu` e `.menu a.ativo`. |
| 4 | Baixa | `SolicitacaoController::update` | `PUT` sem nenhum campo válido (ex.: só `status`) devolve 200 e grava "EDICAO" no histórico, mesmo sem ter mudado nada. | Só gravar o histórico quando `validated()` não estiver vazio (ou responder 422). |
| 5 | Baixa (UX) | Tela de Regulação do NIR | O pedido de informações do regulador (Pendente) aparece rotulado como "Parecer:", igual ao parecer da triagem. | Mostrar só o parecer da triagem, ou diferenciar o rótulo. |

**Observações (não são falhas):**
- O histórico é ordenado só por `data_hora` (precisão de segundos). Ações no mesmo segundo dependem da ordem de retorno do banco. Funcionou em todas as execuções, mas ordenar também por `id_historico` deixaria a ordem garantida.
- Os tokens do Sanctum não expiram (`expiration => null`). Vale definir um prazo antes de ir para produção.
- As listas são ordenadas da solicitação mais antiga para a mais nova; no Acompanhamento, a recém-criada fica no fim da tabela.

## O que não foi coberto

- **AGHU real** (`AGHU_MOCK=false`): depende do sistema do hospital. Mapeamento de campos, 404 e não duplicar paciente estão cobertos pelo PHPUnit com respostas HTTP simuladas (`PacienteAghuTest`). **AGHU fora do ar não tem teste automatizado**; foi verificado à mão e gerou o problema 1.
- **Isolamento entre dois médicos solicitantes** no teste de API: o seed tem um só solicitante e a API não cria usuários. Está coberto pelo PHPUnit: lista e detalhe em `TriagemRegulacaoTest`, edição em `SolicitacaoTest`.
- Layout em celular e navegadores além do Chromium.

## Como reproduzir

Com o banco migrado e populado (`php artisan migrate --seed`), o backend (`php artisan serve`) e o frontend (`npm start`) rodando:

```bash
# Backend: testes automatizados + relatórios
cd backend
php vendor/bin/phpunit --log-junit ../testes-integracao/evidencias/phpunit-junit.xml --testdox-text ../testes-integracao/evidencias/phpunit-testdox.txt

# Integração ponta a ponta (da raiz do projeto)
node testes-integracao/fluxo-completo.mjs
```

O script não precisa de dependências (Node 18+), pode rodar quantas vezes quiser no mesmo banco e termina com código 1 se alguma verificação falhar. Para apontar para outro endereço: `BASE_URL=http://localhost:4200 BACKEND_URL=http://127.0.0.1:8000`.
