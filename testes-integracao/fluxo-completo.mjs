/**
 * Teste de integração ponta a ponta — Integra / NIR
 *
 * Percorre o fluxo completo da consultoria ambulatorial pela API REAL, passando
 * pelo proxy do Angular (localhost:4200/api → Laravel :8000 → PostgreSQL), com os
 * três perfis: médico solicitante, médico regulador e NIR.
 *
 * Pré-requisitos: banco migrado com seed, `php artisan serve` e `npm start` rodando.
 *
 * Uso:
 *   node testes-integracao/fluxo-completo.mjs
 *   BASE_URL=http://localhost:4200 BACKEND_URL=http://127.0.0.1:8000 node testes-integracao/fluxo-completo.mjs
 *
 * Saída: ✔/✘ por verificação no terminal e os arquivos
 *   testes-integracao/evidencias/integracao-api.txt  (log legível)
 *   testes-integracao/evidencias/integracao-api.json (resultado estruturado)
 * O processo termina com código 1 se alguma verificação falhar.
 *
 * Pode ser executado várias vezes: cada execução cria as próprias solicitações
 * e só verifica os registros que ela mesma criou.
 */

import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE_URL = process.env.BASE_URL ?? 'http://localhost:4200';
const BACKEND_URL = process.env.BACKEND_URL ?? 'http://127.0.0.1:8000';
const SENHA = 'senha123';
const USUARIOS = {
  medico: 'medico@hospital.gov.br',
  regulador: 'regulador@hospital.gov.br',
  nir: 'nir@hospital.gov.br',
};
const PRONTUARIOS_AGHU = ['123456', '654321', '345678', '778899', '889900', '990011'];

const pastaEvidencias = join(dirname(fileURLToPath(import.meta.url)), 'evidencias');
const resultados = [];
const linhas = [];
let secaoAtual = '';

// ─── Utilitários ──────────────────────────────────────────────────────────────

function log(texto = '') {
  console.log(texto);
  linhas.push(texto);
}

function secao(nome) {
  secaoAtual = nome;
  log('');
  log(`■ ${nome}`);
}

class FalhaDeVerificacao extends Error {}

function verificar(condicao, mensagem) {
  if (!condicao) {
    throw new FalhaDeVerificacao(mensagem);
  }
}

function verificarStatus(resposta, esperado) {
  verificar(
    resposta.status === esperado,
    `esperava HTTP ${esperado}, recebeu ${resposta.status}: ${JSON.stringify(resposta.corpo).slice(0, 300)}`,
  );
}

/** Executa uma verificação; uma falha é registrada e o teste segue para a próxima. */
async function etapa(nome, fn) {
  const inicio = performance.now();
  try {
    await fn();
    const ms = Math.round(performance.now() - inicio);
    resultados.push({ secao: secaoAtual, nome, ok: true, ms });
    log(`  ✔ ${nome} (${ms} ms)`);
  } catch (e) {
    const ms = Math.round(performance.now() - inicio);
    const motivo = e instanceof FalhaDeVerificacao ? e.message : `erro inesperado: ${e?.stack ?? e}`;
    resultados.push({ secao: secaoAtual, nome, ok: false, ms, motivo });
    log(`  ✘ ${nome} (${ms} ms)`);
    log(`      → ${motivo}`);
  }
}

async function api(metodo, caminho, { token, corpo, base = BASE_URL, cabecalhos = {} } = {}) {
  const resposta = await fetch(base + caminho, {
    method: metodo,
    headers: {
      Accept: 'application/json',
      ...(corpo !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...cabecalhos,
    },
    body: corpo !== undefined ? JSON.stringify(corpo) : undefined,
  });
  const texto = await resposta.text();
  let json = null;
  try {
    json = texto ? JSON.parse(texto) : null;
  } catch {
    json = texto;
  }
  return { status: resposta.status, corpo: json, headers: resposta.headers };
}

/** "YYYY-MM-DD HH:mm:00" — mesmo formato que a tela de Regulação envia. */
function dataLocal(diasAPartirDeHoje) {
  const d = new Date(Date.now() + diasAPartirDeHoje * 24 * 60 * 60 * 1000);
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:00`;
}

// ─── Estado compartilhado entre as etapas ────────────────────────────────────

const tokens = {};
const usuarios = {};
const pacientes = {};
let idPrincipal = null; // fluxo: pendência → complemento → triagem → agendamento
let idDevolucao = null; // fluxo: devolução (HU09) → complemento → triagem → devolução do parecer

// ─── Execução ─────────────────────────────────────────────────────────────────

const inicioGeral = new Date();
log('Teste de integração — Integra / NIR');
log(`Data/hora: ${inicioGeral.toLocaleString('pt-BR')}`);
log(`Frontend (proxy): ${BASE_URL}   Backend: ${BACKEND_URL}   Node ${process.version}`);

secao('1. Infraestrutura');

await etapa('Frontend Angular responde e serve a aplicação', async () => {
  const r = await fetch(BASE_URL + '/');
  const html = await r.text();
  verificar(r.status === 200, `HTTP ${r.status}`);
  verificar(html.includes('<app-root'), 'index.html não contém <app-root>');
});

await etapa('Backend Laravel responde no health check (/up)', async () => {
  const r = await fetch(BACKEND_URL + '/up');
  verificar(r.status === 200, `HTTP ${r.status}`);
});

await etapa('Proxy do Angular encaminha /api ao backend (401 em JSON sem token)', async () => {
  const r = await api('GET', '/api/auth/me');
  verificarStatus(r, 401);
  verificar(r.corpo?.message === 'Unauthenticated.', `corpo inesperado: ${JSON.stringify(r.corpo)}`);
});

await etapa('CORS libera o frontend (preflight com Origin http://localhost:4200)', async () => {
  const r = await fetch(BACKEND_URL + '/api/auth/login', {
    method: 'OPTIONS',
    headers: {
      Origin: 'http://localhost:4200',
      'Access-Control-Request-Method': 'POST',
      'Access-Control-Request-Headers': 'content-type,authorization',
    },
  });
  verificar(r.status === 204 || r.status === 200, `HTTP ${r.status}`);
  const origem = r.headers.get('access-control-allow-origin');
  verificar(origem === 'http://localhost:4200', `Access-Control-Allow-Origin = ${origem}`);
});

secao('2. Autenticação (Sanctum)');

await etapa('Login com senha errada → 401 sem revelar qual campo falhou', async () => {
  const r = await api('POST', '/api/auth/login', { corpo: { email: USUARIOS.medico, password: 'errada' } });
  verificarStatus(r, 401);
  verificar(r.corpo?.message === 'Credenciais inválidas.', `mensagem: ${r.corpo?.message}`);
});

await etapa('Login com e-mail inválido → 422', async () => {
  const r = await api('POST', '/api/auth/login', { corpo: { email: 'nao-e-email', password: SENHA } });
  verificarStatus(r, 422);
  verificar(r.corpo?.errors?.email, 'sem erro de validação no campo email');
});

for (const [perfil, email] of Object.entries(USUARIOS)) {
  await etapa(`Login como ${perfil} devolve token e perfil corretos`, async () => {
    const r = await api('POST', '/api/auth/login', { corpo: { email, password: SENHA } });
    verificarStatus(r, 200);
    verificar(typeof r.corpo?.token === 'string' && r.corpo.token.includes('|'), 'token ausente ou fora do formato do Sanctum');
    const esperado = { medico: 'solicitante', regulador: 'regulador', nir: 'nir' }[perfil];
    verificar(r.corpo.usuario?.perfil === esperado, `perfil = ${r.corpo.usuario?.perfil}`);
    tokens[perfil] = r.corpo.token;
    usuarios[perfil] = r.corpo.usuario;
  });
}

await etapa('GET /api/auth/me devolve nome, CRM e perfil do médico (pré-preenchimento HU01)', async () => {
  const r = await api('GET', '/api/auth/me', { token: tokens.medico });
  verificarStatus(r, 200);
  verificar(r.corpo.email === USUARIOS.medico, `email = ${r.corpo.email}`);
  verificar(r.corpo.crm === usuarios.medico.crm && r.corpo.crm, `crm = ${r.corpo.crm}`);
  verificar(r.corpo.perfil === 'solicitante', `perfil = ${r.corpo.perfil}`);
});

await etapa('Token inventado é recusado → 401', async () => {
  const r = await api('GET', '/api/auth/me', { token: '999|token-que-nao-existe' });
  verificarStatus(r, 401);
});

secao('3. Pacientes — integração com o AGHU (modo simulado) e gravação no PostgreSQL');

for (const prontuario of PRONTUARIOS_AGHU) {
  await etapa(`Prontuário ${prontuario}: dados do AGHU + idPaciente gravado`, async () => {
    const r = await api('GET', `/api/pacientes/${prontuario}`, { token: tokens.medico });
    verificarStatus(r, 200);
    verificar(r.corpo.prontuario === prontuario, `prontuario = ${r.corpo.prontuario}`);
    verificar(r.corpo.nomePaciente, 'nomePaciente vazio');
    verificar(Number.isInteger(r.corpo.idPaciente), `idPaciente = ${r.corpo.idPaciente}`);
    pacientes[prontuario] = r.corpo;
  });
}

await etapa('Buscar o mesmo prontuário de novo não duplica o paciente (mesmo idPaciente)', async () => {
  const r = await api('GET', '/api/pacientes/990011', { token: tokens.medico });
  verificarStatus(r, 200);
  verificar(r.corpo.idPaciente === pacientes['990011'].idPaciente, `${r.corpo.idPaciente} ≠ ${pacientes['990011'].idPaciente}`);
});

await etapa('Prontuário inexistente → 404 com mensagem', async () => {
  const r = await api('GET', '/api/pacientes/000000', { token: tokens.medico });
  verificarStatus(r, 404);
  verificar(r.corpo?.message === 'Paciente não encontrado no AGHU.', `mensagem: ${r.corpo?.message}`);
});

await etapa('Buscar paciente sem login → 401', async () => {
  const r = await api('GET', '/api/pacientes/123456');
  verificarStatus(r, 401);
});

secao('4. Criação da solicitação — validações e permissões (HU02)');

const novaSolicitacao = (prontuario, extra = {}) => ({
  id_paciente: pacientes[prontuario]?.idPaciente,
  especialidade: 'Pneumologia',
  descricao_clinica: 'Tosse produtiva há 10 semanas, dispneia aos esforços, ex-tabagista. [teste de integração]',
  ...extra,
});

await etapa('Regulador não pode criar solicitação → 403', async () => {
  const r = await api('POST', '/api/solicitacoes', { token: tokens.regulador, corpo: novaSolicitacao('990011') });
  verificarStatus(r, 403);
});

await etapa('Descrição clínica com menos de 20 caracteres → 422', async () => {
  const r = await api('POST', '/api/solicitacoes', {
    token: tokens.medico,
    corpo: novaSolicitacao('990011', { descricao_clinica: 'curta demais' }),
  });
  verificarStatus(r, 422);
  verificar(r.corpo?.errors?.descricao_clinica, 'sem erro em descricao_clinica');
});

await etapa('Clínica destino fora da lista → 422', async () => {
  const r = await api('POST', '/api/solicitacoes', {
    token: tokens.medico,
    corpo: novaSolicitacao('990011', { especialidade: 'Cardiologia' }),
  });
  verificarStatus(r, 422);
  verificar(r.corpo?.errors?.especialidade, 'sem erro em especialidade');
});

await etapa('Paciente que não existe no banco → 422', async () => {
  const r = await api('POST', '/api/solicitacoes', {
    token: tokens.medico,
    corpo: novaSolicitacao('990011', { id_paciente: 99999999 }),
  });
  verificarStatus(r, 422);
  verificar(r.corpo?.errors?.id_paciente, 'sem erro em id_paciente');
});

secao('5. Fluxo principal: criação → pendência → complemento → triagem → agendamento');

await etapa('Médico cria a solicitação → 201 "Aguarda triagem"', async () => {
  const r = await api('POST', '/api/solicitacoes', { token: tokens.medico, corpo: novaSolicitacao('990011') });
  verificarStatus(r, 201);
  verificar(r.corpo.status === 'Aguarda triagem', `status = ${r.corpo.status}`);
  idPrincipal = r.corpo.id;
  log(`      (solicitação #${idPrincipal})`);
});

await etapa('Lista do médico contém a nova solicitação e só solicitações dele', async () => {
  const r = await api('GET', '/api/solicitacoes', { token: tokens.medico });
  verificarStatus(r, 200);
  verificar(Array.isArray(r.corpo), 'resposta não é uma lista');
  verificar(r.corpo.some((s) => s.id === idPrincipal), `#${idPrincipal} não está na lista`);
  const deOutros = r.corpo.filter((s) => s.solicitante?.id !== usuarios.medico.id);
  verificar(deOutros.length === 0, `${deOutros.length} solicitação(ões) de outro médico na lista`);
});

await etapa('Regulador encontra a solicitação pela busca por prontuário', async () => {
  const r = await api('GET', '/api/solicitacoes?busca=990011', { token: tokens.regulador });
  verificarStatus(r, 200);
  verificar(r.corpo.some((s) => s.id === idPrincipal), `#${idPrincipal} não veio na busca`);
  const outros = r.corpo.filter((s) => s.paciente?.prontuario !== '990011');
  verificar(outros.length === 0, `busca trouxe ${outros.length} solicitação(ões) de outro paciente`);
});

await etapa('Busca por nome do paciente ignora maiúsculas/minúsculas', async () => {
  const r = await api('GET', `/api/solicitacoes?busca=${encodeURIComponent('roberto CARLOS')}`, { token: tokens.regulador });
  verificarStatus(r, 200);
  verificar(r.corpo.some((s) => s.id === idPrincipal), `#${idPrincipal} não veio na busca por nome`);
});

await etapa('Filtros de status e clínica do regulador funcionam', async () => {
  const r = await api('GET', `/api/solicitacoes?status=${encodeURIComponent('Aguarda triagem')}&especialidade=Pneumologia`, {
    token: tokens.regulador,
  });
  verificarStatus(r, 200);
  verificar(r.corpo.some((s) => s.id === idPrincipal), `#${idPrincipal} não veio no filtro`);
  verificar(
    r.corpo.every((s) => s.status === 'Aguarda triagem' && s.especialidade === 'Pneumologia'),
    'filtro devolveu itens com outro status/clínica',
  );
});

await etapa('Médico edita a própria solicitação (PUT) → 200', async () => {
  const descricao = 'Tosse produtiva há 10 semanas, dispneia aos esforços, ex-tabagista 30 maços/ano. [teste de integração]';
  const r = await api('PUT', `/api/solicitacoes/${idPrincipal}`, { token: tokens.medico, corpo: { descricao_clinica: descricao } });
  verificarStatus(r, 200);
  verificar(r.corpo.descricaoClinica === descricao, 'descrição não foi atualizada');
  verificar(r.corpo.status === 'Aguarda triagem', `status mudou para ${r.corpo.status}`);
});

await etapa('PUT não permite trocar o status pela API (campo ignorado)', async () => {
  const r = await api('PUT', `/api/solicitacoes/${idPrincipal}`, { token: tokens.medico, corpo: { status: 'Finalizado' } });
  verificarStatus(r, 200);
  verificar(r.corpo.status === 'Aguarda triagem', `status virou ${r.corpo.status}`);
});

await etapa('Não existe rota DELETE de solicitação → 405', async () => {
  const r = await api('DELETE', `/api/solicitacoes/${idPrincipal}`, { token: tokens.medico });
  verificarStatus(r, 405);
});

await etapa('Regulador marca "Pendente" pedindo informações → 201', async () => {
  const r = await api('POST', '/api/pareceres', {
    token: tokens.regulador,
    corpo: { id_solicitacao: idPrincipal, texto_parecer: 'Anexar espirometria e radiografia de tórax recentes.', status: 'Pendente' },
  });
  verificarStatus(r, 201);
  verificar(r.corpo.status === 'Pendente', `status = ${r.corpo.status}`);
});

await etapa('NIR não consegue regular solicitação Pendente → 422', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/regulacao`, {
    token: tokens.nir,
    corpo: { conduta: 'agendamento', justificativa: 'Vaga disponível.', data_agendamento: dataLocal(7), local: 'Sala 1' },
  });
  verificarStatus(r, 422);
});

await etapa('Médico vê o motivo da pendência no histórico', async () => {
  const r = await api('GET', `/api/solicitacoes/${idPrincipal}`, { token: tokens.medico });
  verificarStatus(r, 200);
  verificar(r.corpo.status === 'Pendente', `status = ${r.corpo.status}`);
  const pendencia = r.corpo.historico?.find((h) => h.acao === 'PENDENCIA');
  verificar(pendencia?.descricao?.includes('espirometria'), 'PENDENCIA com o motivo não está no histórico');
});

await etapa('Complemento com menos de 10 caracteres → 422', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/complementar`, { token: tokens.medico, corpo: { texto: 'curto' } });
  verificarStatus(r, 422);
});

await etapa('Regulador não pode complementar no lugar do médico → 403', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/complementar`, {
    token: tokens.regulador,
    corpo: { texto: 'Tentativa indevida de complementar.' },
  });
  verificarStatus(r, 403);
});

await etapa('Médico complementa → volta para "Aguarda triagem"', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/complementar`, {
    token: tokens.medico,
    corpo: { texto: 'Espirometria 15/11: VEF1 62% do previsto. RX com hiperinsuflação, sem massas.' },
  });
  verificarStatus(r, 200);
  verificar(r.corpo.status === 'Aguarda triagem', `status = ${r.corpo.status}`);
});

await etapa('Complementar de novo (não está mais Pendente) → 403', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/complementar`, {
    token: tokens.medico,
    corpo: { texto: 'Segunda tentativa de complemento.' },
  });
  verificarStatus(r, 403);
});

await etapa('Triado sem prioridade → 422', async () => {
  const r = await api('POST', '/api/pareceres', {
    token: tokens.regulador,
    corpo: { id_solicitacao: idPrincipal, texto_parecer: 'Indicação de avaliação pela pneumologia.', status: 'Triado' },
  });
  verificarStatus(r, 422);
  verificar(r.corpo?.errors?.prioridade, 'sem erro em prioridade');
});

await etapa('Médico não pode emitir parecer → 403', async () => {
  const r = await api('POST', '/api/pareceres', {
    token: tokens.medico,
    corpo: { id_solicitacao: idPrincipal, texto_parecer: 'Tentativa indevida de parecer.', status: 'Triado', prioridade: 'P1' },
  });
  verificarStatus(r, 403);
});

await etapa('Regulador tria com prioridade P2 → "Triado"', async () => {
  const r = await api('POST', '/api/pareceres', {
    token: tokens.regulador,
    corpo: { id_solicitacao: idPrincipal, texto_parecer: 'DPOC provável, indicação de avaliação pela pneumologia.', status: 'Triado', prioridade: 'P2' },
  });
  verificarStatus(r, 201);
  verificar(r.corpo.status === 'Triado', `status = ${r.corpo.status}`);
});

await etapa('Filtro de prioridade P2 traz a solicitação; NIR a vê entre as triadas', async () => {
  const r1 = await api('GET', '/api/solicitacoes?prioridade=P2', { token: tokens.regulador });
  verificarStatus(r1, 200);
  verificar(r1.corpo.some((s) => s.id === idPrincipal && s.prioridade === 'P2'), 'não veio no filtro P2');
  const r2 = await api('GET', '/api/solicitacoes?status=Triado', { token: tokens.nir });
  verificarStatus(r2, 200);
  verificar(r2.corpo.some((s) => s.id === idPrincipal), 'NIR não vê a solicitação triada');
});

await etapa('Médico não pode mais editar depois da triagem → 403', async () => {
  const r = await api('PUT', `/api/solicitacoes/${idPrincipal}`, {
    token: tokens.medico,
    corpo: { descricao_clinica: 'Tentando alterar depois da triagem, não deveria.' },
  });
  verificarStatus(r, 403);
});

await etapa('Regulador não pode fazer a regulação do NIR → 403', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/regulacao`, {
    token: tokens.regulador,
    corpo: { conduta: 'agendamento', justificativa: 'Vaga disponível.', data_agendamento: dataLocal(7), local: 'Sala 1' },
  });
  verificarStatus(r, 403);
});

await etapa('Agendamento com data no passado → 422', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/regulacao`, {
    token: tokens.nir,
    corpo: { conduta: 'agendamento', justificativa: 'Vaga disponível.', data_agendamento: dataLocal(-1), local: 'Sala 1' },
  });
  verificarStatus(r, 422);
  verificar(r.corpo?.errors?.data_agendamento, 'sem erro em data_agendamento');
});

await etapa('Agendamento sem local → 422', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/regulacao`, {
    token: tokens.nir,
    corpo: { conduta: 'agendamento', justificativa: 'Vaga disponível.', data_agendamento: dataLocal(7) },
  });
  verificarStatus(r, 422);
  verificar(r.corpo?.errors?.local, 'sem erro em local');
});

const dataAgendada = dataLocal(10);
await etapa('NIR agenda a consulta → "Finalizado"', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/regulacao`, {
    token: tokens.nir,
    corpo: { conduta: 'agendamento', justificativa: 'Vaga disponível na agenda da pneumologia.', data_agendamento: dataAgendada, local: 'Ambulatório HUB - sala 7' },
  });
  verificarStatus(r, 200);
  verificar(r.corpo.status === 'Finalizado', `status = ${r.corpo.status}`);
});

await etapa('Médico vê Finalizado + prioridade + agendamento + pareceres', async () => {
  const r = await api('GET', `/api/solicitacoes/${idPrincipal}`, { token: tokens.medico });
  verificarStatus(r, 200);
  const s = r.corpo;
  verificar(s.status === 'Finalizado', `status = ${s.status}`);
  verificar(s.prioridade === 'P2', `prioridade = ${s.prioridade}`);
  verificar(s.agendamento?.local === 'Ambulatório HUB - sala 7', `local = ${s.agendamento?.local}`);
  const agendadoPara = new Date(s.agendamento?.data);
  const esperado = new Date(dataAgendada.replace(' ', 'T'));
  verificar(agendadoPara.getTime() === esperado.getTime(), `data agendada ${s.agendamento?.data} ≠ ${dataAgendada} (fuso horário?)`);
  verificar(s.pareceres?.length === 2, `pareceres = ${s.pareceres?.length} (esperava 2: pendência e triagem)`);
});

await etapa('Histórico registra todas as ações na ordem certa', async () => {
  const r = await api('GET', `/api/solicitacoes/${idPrincipal}`, { token: tokens.regulador });
  verificarStatus(r, 200);
  const acoes = r.corpo.historico.map((h) => h.acao);
  const esperado = ['CRIACAO', 'EDICAO', 'EDICAO', 'PENDENCIA', 'COMPLEMENTO', 'TRIAGEM', 'REGULACAO'];
  verificar(JSON.stringify(acoes) === JSON.stringify(esperado), `histórico = ${acoes.join(' → ')}`);
  verificar(r.corpo.historico.every((h) => h.dataHora && Number.isInteger(h.usuario)), 'registro sem data ou usuário');
});

await etapa('Solicitação finalizada não recebe novo parecer → 422', async () => {
  const r = await api('POST', '/api/pareceres', {
    token: tokens.regulador,
    corpo: { id_solicitacao: idPrincipal, texto_parecer: 'Parecer depois de finalizada.', status: 'Pendente' },
  });
  verificarStatus(r, 422);
});

await etapa('Solicitação finalizada não pode ser devolvida → 422', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/devolver`, {
    token: tokens.regulador,
    corpo: { motivo: 'Tentativa de devolver depois de finalizada.' },
  });
  verificarStatus(r, 422);
});

await etapa('Solicitação finalizada não pode ser regulada de novo → 422', async () => {
  const r = await api('POST', `/api/solicitacoes/${idPrincipal}/regulacao`, {
    token: tokens.nir,
    corpo: { conduta: 'devolucao_parecer', justificativa: 'Segunda regulação.' },
  });
  verificarStatus(r, 422);
});

secao('6. Devolução (HU09) e devolução do parecer pelo NIR');

await etapa('Médico cria a segunda solicitação', async () => {
  const r = await api('POST', '/api/solicitacoes', {
    token: tokens.medico,
    corpo: novaSolicitacao('654321', {
      especialidade: 'Gastroenterologia',
      descricao_clinica: 'Epigastralgia há 4 semanas sem sinais de alarme. [teste de integração]',
    }),
  });
  verificarStatus(r, 201);
  idDevolucao = r.corpo.id;
  log(`      (solicitação #${idDevolucao})`);
});

await etapa('Médico não pode devolver a própria solicitação → 403', async () => {
  const r = await api('POST', `/api/solicitacoes/${idDevolucao}/devolver`, {
    token: tokens.medico,
    corpo: { motivo: 'Tentativa indevida de devolução.' },
  });
  verificarStatus(r, 403);
});

await etapa('Devolução com motivo curto → 422 e status não muda', async () => {
  const r = await api('POST', `/api/solicitacoes/${idDevolucao}/devolver`, { token: tokens.regulador, corpo: { motivo: 'curto' } });
  verificarStatus(r, 422);
  const d = await api('GET', `/api/solicitacoes/${idDevolucao}`, { token: tokens.regulador });
  verificar(d.corpo.status === 'Aguarda triagem', `status mudou para ${d.corpo.status}`);
});

await etapa('Regulador devolve com motivo → "Pendente" e motivo no histórico', async () => {
  const motivo = 'Informar uso de AINEs e resultado de pesquisa de H. pylori.';
  const r = await api('POST', `/api/solicitacoes/${idDevolucao}/devolver`, { token: tokens.regulador, corpo: { motivo } });
  verificarStatus(r, 200);
  verificar(r.corpo.status === 'Pendente' && r.corpo.motivo === motivo, JSON.stringify(r.corpo));
  const d = await api('GET', `/api/solicitacoes/${idDevolucao}`, { token: tokens.medico });
  verificar(d.corpo.historico.some((h) => h.acao === 'DEVOLUCAO' && h.descricao === motivo), 'DEVOLUCAO não está no histórico');
});

await etapa('Médico complementa a devolução → "Aguarda triagem"', async () => {
  const r = await api('POST', `/api/solicitacoes/${idDevolucao}/complementar`, {
    token: tokens.medico,
    corpo: { texto: 'Nega uso de AINEs. H. pylori não pesquisado.' },
  });
  verificarStatus(r, 200);
  verificar(r.corpo.status === 'Aguarda triagem', `status = ${r.corpo.status}`);
});

await etapa('Regulador tria com P3', async () => {
  const r = await api('POST', '/api/pareceres', {
    token: tokens.regulador,
    corpo: { id_solicitacao: idDevolucao, texto_parecer: 'Caso de atenção básica: tratar empiricamente e reavaliar.', status: 'Triado', prioridade: 'P3' },
  });
  verificarStatus(r, 201);
  verificar(r.corpo.status === 'Triado', `status = ${r.corpo.status}`);
});

await etapa('NIR devolve o parecer ao solicitante → "Finalizado" sem agendamento', async () => {
  const r = await api('POST', `/api/solicitacoes/${idDevolucao}/regulacao`, {
    token: tokens.nir,
    corpo: { conduta: 'devolucao_parecer', justificativa: 'P3: seguir orientação do parecer na atenção básica.' },
  });
  verificarStatus(r, 200);
  verificar(r.corpo.status === 'Finalizado' && r.corpo.conduta === 'devolucao_parecer', JSON.stringify(r.corpo));
  const d = await api('GET', `/api/solicitacoes/${idDevolucao}`, { token: tokens.medico });
  verificar(d.corpo.agendamento === null, `agendamento = ${JSON.stringify(d.corpo.agendamento)}`);
  const acoes = d.corpo.historico.map((h) => h.acao);
  const esperado = ['CRIACAO', 'DEVOLUCAO', 'COMPLEMENTO', 'TRIAGEM', 'REGULACAO'];
  verificar(JSON.stringify(acoes) === JSON.stringify(esperado), `histórico = ${acoes.join(' → ')}`);
});

secao('7. Outros acessos e encerramento de sessão');

await etapa('Solicitação inexistente → 404', async () => {
  const r = await api('GET', '/api/solicitacoes/99999999', { token: tokens.regulador });
  verificarStatus(r, 404);
});

await etapa('Parecer para solicitação inexistente → 422', async () => {
  const r = await api('POST', '/api/pareceres', {
    token: tokens.regulador,
    corpo: { id_solicitacao: 99999999, texto_parecer: 'Solicitação que não existe.', status: 'Pendente' },
  });
  verificarStatus(r, 422);
});

for (const perfil of Object.keys(USUARIOS)) {
  await etapa(`Logout do ${perfil} revoga o token (depois disso → 401)`, async () => {
    const r = await api('POST', '/api/auth/logout', { token: tokens[perfil] });
    verificarStatus(r, 200);
    const depois = await api('GET', '/api/auth/me', { token: tokens[perfil] });
    verificarStatus(depois, 401);
  });
}

// ─── Resumo e evidências ──────────────────────────────────────────────────────

const total = resultados.length;
const falhas = resultados.filter((r) => !r.ok);
log('');
log('─'.repeat(60));
log(`Resultado: ${total - falhas.length}/${total} verificações passaram` + (falhas.length ? ` — ${falhas.length} FALHA(S)` : ''));
for (const f of falhas) {
  log(`  ✘ [${f.secao}] ${f.nome}`);
}
log(`Duração: ${((Date.now() - inicioGeral.getTime()) / 1000).toFixed(1)} s`);

mkdirSync(pastaEvidencias, { recursive: true });
writeFileSync(join(pastaEvidencias, 'integracao-api.txt'), linhas.join('\n') + '\n', 'utf8');
writeFileSync(
  join(pastaEvidencias, 'integracao-api.json'),
  JSON.stringify(
    {
      dataHora: inicioGeral.toISOString(),
      baseUrl: BASE_URL,
      backendUrl: BACKEND_URL,
      node: process.version,
      total,
      aprovadas: total - falhas.length,
      falhas: falhas.length,
      solicitacoesCriadas: [idPrincipal, idDevolucao],
      verificacoes: resultados,
    },
    null,
    2,
  ) + '\n',
  'utf8',
);

process.exit(falhas.length ? 1 : 0);
