export type Perfil = 'solicitante' | 'regulador' | 'nir';
export type Status = 'Aguarda triagem' | 'Triado' | 'Pendente' | 'Finalizado';

export interface Usuario {
  id: number;
  nome: string;
  email?: string;
  crm: string | null;
  perfil: Perfil;
}

export interface LoginResposta {
  token: string;
  usuario: Usuario;
}

export interface PacienteAghu {
  prontuario: string;
  nomePaciente: string;
  cartaoSus: string | null;
  cpf: string | null;
  leito: string | null;
  numeroConsulta: string | null;
  clinicaOrigem: string | null;
  idPaciente: number;
}

export interface Solicitacao {
  id: number;
  status: Status;
  especialidade: string;
  descricaoClinica: string;
  dataSolicitacao: string | null;
  prioridade?: string | null;
  paciente?: { id: number; prontuario: string; nome: string | null; cartaoSus: string | null; leito: string | null } | null;
  solicitante?: { id: number; nome: string; crm: string | null } | null;
  pareceres?: { id: number; texto: string; data: string | null }[];
  agendamento?: { data: string | null; local: string } | null;
  historico?: { acao: string; descricao: string | null; dataHora: string | null; usuario: number }[];
}

export const ESPECIALIDADES: string[] = [
  'Ginecologia cirúrgica', 'Cirurgia geral', 'Endocrinologia adulto', 'Endocrinologia pediátrica',
  'Gastroenterologia', 'Pneumologia', 'Proctologia', 'Urologia', 'Fisioterapia', 'Nutrição',
  'Fonoaudiologia', 'Terapia ocupacional',
];

export const STATUS_LISTA: Status[] = ['Aguarda triagem', 'Triado', 'Pendente', 'Finalizado'];
