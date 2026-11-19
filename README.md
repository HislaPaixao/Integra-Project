# Integra / NIR — Módulo de Consultoria Ambulatorial

Angular 18 (frontend) + Laravel 12 (backend) + PostgreSQL 16.

## Como rodar (3 terminais)

**Pré-requisitos:** Docker, PHP 8.2+ com extensão `pdo_pgsql`, Composer, Node 18+.

### 1. Banco de dados
```bash
docker compose up -d        # sobe o PostgreSQL (db integra_nir, usuário/senha integra)
```
Sem Docker? Use qualquer PostgreSQL e ajuste `DB_*` no `backend/.env`.

### 2. Backend (porta 8000)
```bash
cd backend
cp .env.example .env        # no Windows: copy .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed  # cria tabelas + usuários + solicitações de demonstração
php artisan serve           # http://127.0.0.1:8000
```
Swagger: http://127.0.0.1:8000/api/documentation

### 3. Frontend (porta 4200)
```bash
cd frontend
npm install
npm start                   # http://localhost:4200 (o /api é encaminhado ao backend)
```

## Usuários de demonstração (senha `senha123`)
| Perfil | E-mail | Tela |
|---|---|---|
| Médico solicitante | medico@hospital.gov.br | Nova Solicitação, Acompanhamento |
| Médico regulador | regulador@hospital.gov.br | Avaliação de Parecer |
| NIR | nir@hospital.gov.br | Regulação NIR |

## Pacientes do AGHU (modo simulado, `AGHU_MOCK=true`)
Prontuários: `123456`, `654321`, `345678`, `778899`, `889900`, `990011`.
Em produção: `AGHU_MOCK=false` e preencher `AGHU_BASE_URL` / `AGHU_TOKEN` no `.env`.

## Fluxo
1. **Solicitante** cria a solicitação → *Aguarda triagem*.
2. **Regulador** emite parecer: *Triado* (com prioridade P1/P2/P3) ou *Pendente* (pede informações; o motivo fica no histórico).
3. **Solicitante** complementa uma solicitação *Pendente* → volta para *Aguarda triagem*.
4. **NIR** regula uma solicitação *Triado*: agendamento (data + local) ou devolução do parecer → *Finalizado*.
Toda ação grava no histórico (CRIACAO, TRIAGEM, PENDENCIA, COMPLEMENTO, REGULACAO, DEVOLUCAO).

## Roteiro de demonstração (mentor)
1. Login como médico → *Nova Solicitação*: prontuário `123456` (carrega do AGHU), escolher clínica, descrição ≥ 20 caracteres, enviar.
2. Sair → login como regulador → *Avaliar* a solicitação → Pendente com motivo.
3. Voltar ao médico → *Acompanhamento* → abrir → enviar informações complementares.
4. Regulador → Triado com P2. 5. NIR → Regular → agendamento. 6. Médico vê *Finalizado* + agendamento + histórico.
7. Mostrar no Swagger: login/logout reais, 404 de paciente inexistente, devolução com motivo, sem rota DELETE.

## Testes do backend
```bash
cd backend && php artisan test      # 44 testes (SQLite em memória, não mexe no seu PostgreSQL)
```

## Correções da revisão do mentor (Swagger)
1. DELETE de solicitação: rota removida (solicitação não se exclui; histórico preservado).
2. `UpdateSolicitacaoRequest` criado (só o dono, e só em *Aguarda triagem*/*Pendente*).
3. Login/logout reais com Laravel Sanctum (sem usuário fixo nem `demo-token`).
4. Paciente é validado (`exists`) antes de criar a solicitação; busca no AGHU grava o paciente.
5. Devolução → *Pendente* correto; motivo obrigatório e registrado no histórico.

## Fora de escopo por enquanto
Funções de IA (descrição, resumo, prioridade sugerida, parecer) foram removidas; o frontend ainda não tem testes automatizados.
