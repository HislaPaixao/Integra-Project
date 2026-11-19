import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PacienteAghu, Solicitacao } from './models';

export interface FiltrosSolicitacao {
  status?: string;
  especialidade?: string;
  prioridade?: string;
  busca?: string;
}

@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);

  buscarPaciente(prontuario: string): Observable<PacienteAghu> {
    return this.http.get<PacienteAghu>(`/api/pacientes/${encodeURIComponent(prontuario.trim())}`);
  }

  listar(filtros: FiltrosSolicitacao = {}): Observable<Solicitacao[]> {
    let params = new HttpParams();
    for (const [chave, valor] of Object.entries(filtros)) {
      if (valor) {
        params = params.set(chave, valor);
      }
    }
    return this.http.get<Solicitacao[]>('/api/solicitacoes', { params });
  }

  detalhar(id: number): Observable<Solicitacao> {
    return this.http.get<Solicitacao>(`/api/solicitacoes/${id}`);
  }

  criar(dados: { id_paciente: number; especialidade: string; descricao_clinica: string }): Observable<{ id: number; status: string }> {
    return this.http.post<{ id: number; status: string }>('/api/solicitacoes', dados);
  }

  complementar(id: number, texto: string): Observable<{ status: string }> {
    return this.http.post<{ status: string }>(`/api/solicitacoes/${id}/complementar`, { texto });
  }

  emitirParecer(dados: {
    id_solicitacao: number;
    texto_parecer: string;
    status: 'Triado' | 'Pendente';
    prioridade?: string;
  }): Observable<{ id: number; status: string }> {
    return this.http.post<{ id: number; status: string }>('/api/pareceres', dados);
  }

  regular(
    id: number,
    dados: { conduta: 'agendamento' | 'devolucao_parecer'; justificativa: string; data_agendamento?: string; local?: string },
  ): Observable<{ status: string }> {
    return this.http.post<{ status: string }>(`/api/solicitacoes/${id}/regulacao`, dados);
  }
}

/** Extrai a primeira mensagem útil de um erro da API (422 de validação, 403, 404...). */
export function mensagemDeErro(erro: unknown, padrao = 'Não foi possível concluir a operação.'): string {
  const e = erro as { status?: number; error?: { message?: string; errors?: Record<string, string[]> } };
  if (e?.status === 0) {
    return 'Sem conexão com o servidor. Verifique se o backend está rodando.';
  }
  const erros = e?.error?.errors;
  if (erros) {
    const primeiro = Object.values(erros)[0];
    if (primeiro && primeiro.length > 0) {
      return primeiro[0] ?? padrao;
    }
  }
  return e?.error?.message ?? padrao;
}
