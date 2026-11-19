import { DatePipe } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService, mensagemDeErro } from '../core/api.service';
import { ESPECIALIDADES, STATUS_LISTA, Solicitacao } from '../core/models';

@Component({
  selector: 'app-avaliacao-parecer',
  standalone: true,
  imports: [FormsModule, DatePipe],
  template: `
    <div class="card">
      <div class="card-header"><h2>Avaliação de Parecer — Médico Regulador</h2></div>
      <div class="card-body">
        <div class="filtros">
          <input name="busca" [(ngModel)]="busca" placeholder="Paciente ou prontuário" (keyup.enter)="carregar()" />
          <select name="fStatus" [(ngModel)]="status" (ngModelChange)="carregar()">
            <option value="">Todos os status</option>
            @for (s of statusLista; track s) { <option [value]="s">{{ s }}</option> }
          </select>
          <select name="fEsp" [(ngModel)]="especialidade" (ngModelChange)="carregar()">
            <option value="">Todas as clínicas</option>
            @for (c of clinicas; track c) { <option [value]="c">{{ c }}</option> }
          </select>
          <select name="fPrio" [(ngModel)]="prioridade" (ngModelChange)="carregar()">
            <option value="">Toda prioridade</option>
            <option value="P1">P1</option><option value="P2">P2</option><option value="P3">P3</option>
          </select>
          <button class="btn btn-secundario" type="button" (click)="carregar()">Buscar</button>
        </div>
        @if (erro) { <div class="alerta alerta-erro">{{ erro }}</div> }
        @if (aviso) { <div class="alerta alerta-ok">{{ aviso }}</div> }
        <table class="tabela">
          <thead><tr><th>#</th><th>Data</th><th>Paciente</th><th>Clínica</th><th>Prior.</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @for (s of lista; track s.id) {
              <tr>
                <td>{{ s.id }}</td>
                <td>{{ s.dataSolicitacao | date: 'dd/MM/yyyy' }}</td>
                <td>{{ s.paciente?.nome }}<br /><small>Pront. {{ s.paciente?.prontuario }}</small></td>
                <td>{{ s.especialidade }}</td>
                <td>{{ s.prioridade ?? '—' }}</td>
                <td><span [class]="'selo ' + classe(s.status)">{{ s.status }}</span></td>
                <td><button class="btn btn-primario" type="button" (click)="abrir(s.id)">Avaliar</button></td>
              </tr>
            } @empty {
              <tr><td colspan="7" class="vazio">Nenhuma solicitação encontrada.</td></tr>
            }
          </tbody>
        </table>
      </div>
    </div>

    @if (detalhe; as d) {
      <div class="modal-fundo" (click)="detalhe = null">
        <div class="modal" (click)="$event.stopPropagation()">
          <h3>Solicitação #{{ d.id }} — {{ d.paciente?.nome }}</h3>
          <p><strong>Clínica:</strong> {{ d.especialidade }} · <strong>Solicitante:</strong> {{ d.solicitante?.nome }}
            (CRM {{ d.solicitante?.crm }}) · <strong>Status:</strong> {{ d.status }}</p>
          <p class="texto">{{ d.descricaoClinica }}</p>
          <h4>Histórico</h4>
          <ul class="historico">
            @for (h of d.historico ?? []; track $index) {
              <li><strong>{{ h.acao }}</strong> · {{ h.dataHora | date: 'dd/MM/yyyy HH:mm' }}<br />{{ h.descricao }}</li>
            }
          </ul>
          @if (d.status === 'Finalizado') {
            <div class="alerta">Solicitação finalizada: não pode mais receber parecer.</div>
          } @else {
            <div class="form-row">
              <div class="form-group">
                <label for="decisao">Resultado *</label>
                <select id="decisao" name="decisao" [(ngModel)]="decisao">
                  <option value="Triado">Triado (avaliação concluída)</option>
                  <option value="Pendente">Pendente (solicitar mais informações)</option>
                </select>
              </div>
              @if (decisao === 'Triado') {
                <div class="form-group">
                  <label for="prio">Prioridade *</label>
                  <select id="prio" name="prio" [(ngModel)]="prioridadeEscolhida">
                    <option value="">Selecione</option>
                    <option value="P1">P1 — Alta</option><option value="P2">P2 — Média</option><option value="P3">P3 — Baixa</option>
                  </select>
                </div>
              }
            </div>
            <div class="form-group">
              <label for="parecer">{{ decisao === 'Pendente' ? 'Motivo / informações necessárias *' : 'Parecer *' }}</label>
              <textarea id="parecer" name="parecer" rows="4" [(ngModel)]="texto"></textarea>
            </div>
            @if (erroModal) { <div class="alerta alerta-erro">{{ erroModal }}</div> }
            <button class="btn btn-primario" type="button" [disabled]="!podeSalvar() || enviando" (click)="salvar(d.id)">
              {{ enviando ? 'Salvando...' : 'Salvar parecer' }}
            </button>
          }
          <button class="btn btn-secundario" type="button" (click)="detalhe = null">Fechar</button>
        </div>
      </div>
    }
  `,
})
export class AvaliacaoParecerComponent implements OnInit {
  private readonly api = inject(ApiService);

  readonly clinicas = ESPECIALIDADES;
  readonly statusLista = STATUS_LISTA;
  lista: Solicitacao[] = [];
  busca = '';
  status = '';
  especialidade = '';
  prioridade = '';
  detalhe: Solicitacao | null = null;
  decisao: 'Triado' | 'Pendente' = 'Triado';
  prioridadeEscolhida = '';
  texto = '';
  erro = '';
  erroModal = '';
  aviso = '';
  enviando = false;

  ngOnInit(): void {
    this.carregar();
  }

  carregar(): void {
    this.erro = '';
    this.api
      .listar({ busca: this.busca, status: this.status, especialidade: this.especialidade, prioridade: this.prioridade })
      .subscribe({
        next: (l) => (this.lista = l),
        error: (e: unknown) => (this.erro = mensagemDeErro(e)),
      });
  }

  classe(status: string): string {
    return 'st-' + status.toLowerCase().replace(/\s+/g, '-');
  }

  abrir(id: number): void {
    this.decisao = 'Triado';
    this.prioridadeEscolhida = '';
    this.texto = '';
    this.erroModal = '';
    this.api.detalhar(id).subscribe({
      next: (d) => (this.detalhe = d),
      error: (e: unknown) => (this.erro = mensagemDeErro(e)),
    });
  }

  podeSalvar(): boolean {
    return this.texto.trim().length >= 10 && (this.decisao === 'Pendente' || this.prioridadeEscolhida !== '');
  }

  salvar(id: number): void {
    this.enviando = true;
    this.erroModal = '';
    this.api
      .emitirParecer({
        id_solicitacao: id,
        texto_parecer: this.texto.trim(),
        status: this.decisao,
        prioridade: this.decisao === 'Triado' ? this.prioridadeEscolhida : undefined,
      })
      .subscribe({
        next: (r) => {
          this.enviando = false;
          this.detalhe = null;
          this.aviso = `Parecer da solicitação #${id} registrado. Novo status: ${r.status}.`;
          this.carregar();
        },
        error: (e: unknown) => {
          this.enviando = false;
          this.erroModal = mensagemDeErro(e);
        },
      });
  }
}
