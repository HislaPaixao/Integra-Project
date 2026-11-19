import { DatePipe } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService, mensagemDeErro } from '../core/api.service';
import { Solicitacao } from '../core/models';

@Component({
  selector: 'app-regulacao',
  standalone: true,
  imports: [FormsModule, DatePipe],
  template: `
    <div class="card">
      <div class="card-header"><h2>Regulação — NIR</h2></div>
      <div class="card-body">
        <div class="filtros">
          <select name="fStatus" [(ngModel)]="status" (ngModelChange)="carregar()">
            <option value="Triado">Triadas (aguardando regulação)</option>
            <option value="Finalizado">Finalizadas</option>
            <option value="">Todas</option>
          </select>
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
                <td>{{ s.paciente?.nome }}</td>
                <td>{{ s.especialidade }}</td>
                <td>{{ s.prioridade ?? '—' }}</td>
                <td>{{ s.status }}</td>
                <td><button class="btn btn-primario" type="button" (click)="abrir(s.id)">{{ s.status === 'Triado' ? 'Regular' : 'Ver' }}</button></td>
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
          <p><strong>Clínica:</strong> {{ d.especialidade }} · <strong>Prioridade:</strong> {{ d.prioridade ?? '—' }}</p>
          <p class="texto">{{ d.descricaoClinica }}</p>
          @for (p of d.pareceres ?? []; track p.id) {
            <p class="texto"><strong>Parecer:</strong> {{ p.texto }}</p>
          }
          @if (d.agendamento; as a) {
            <div class="alerta alerta-ok">Agendado para {{ a.data | date: 'dd/MM/yyyy HH:mm' }} — {{ a.local }}</div>
          }
          @if (d.status === 'Triado') {
            <div class="form-group">
              <label for="conduta">Conduta *</label>
              <select id="conduta" name="conduta" [(ngModel)]="conduta">
                <option value="agendamento">Agendamento</option>
                <option value="devolucao_parecer">Devolução do parecer ao solicitante</option>
              </select>
            </div>
            @if (conduta === 'agendamento') {
              <div class="form-row">
                <div class="form-group">
                  <label for="data">Data e hora *</label>
                  <input id="data" name="data" type="datetime-local" [(ngModel)]="data" />
                </div>
                <div class="form-group">
                  <label for="local">Local *</label>
                  <input id="local" name="local" [(ngModel)]="local" placeholder="Ex: Ambulatório HUB - sala 4" />
                </div>
              </div>
            }
            <div class="form-group">
              <label for="just">Justificativa *</label>
              <textarea id="just" name="just" rows="3" [(ngModel)]="justificativa"></textarea>
            </div>
            @if (erroModal) { <div class="alerta alerta-erro">{{ erroModal }}</div> }
            <button class="btn btn-primario" type="button" [disabled]="!podeSalvar() || enviando" (click)="salvar(d.id)">
              {{ enviando ? 'Salvando...' : 'Finalizar regulação' }}
            </button>
          }
          <button class="btn btn-secundario" type="button" (click)="detalhe = null">Fechar</button>
        </div>
      </div>
    }
  `,
})
export class RegulacaoComponent implements OnInit {
  private readonly api = inject(ApiService);

  lista: Solicitacao[] = [];
  status = 'Triado';
  detalhe: Solicitacao | null = null;
  conduta: 'agendamento' | 'devolucao_parecer' = 'agendamento';
  data = '';
  local = '';
  justificativa = '';
  erro = '';
  erroModal = '';
  aviso = '';
  enviando = false;

  ngOnInit(): void {
    this.carregar();
  }

  carregar(): void {
    this.api.listar({ status: this.status }).subscribe({
      next: (l) => (this.lista = l),
      error: (e: unknown) => (this.erro = mensagemDeErro(e)),
    });
  }

  abrir(id: number): void {
    this.conduta = 'agendamento';
    this.data = '';
    this.local = '';
    this.justificativa = '';
    this.erroModal = '';
    this.api.detalhar(id).subscribe({
      next: (d) => (this.detalhe = d),
      error: (e: unknown) => (this.erro = mensagemDeErro(e)),
    });
  }

  podeSalvar(): boolean {
    const base = this.justificativa.trim().length >= 5;
    return this.conduta === 'agendamento' ? base && this.data !== '' && this.local.trim() !== '' : base;
  }

  salvar(id: number): void {
    this.enviando = true;
    this.erroModal = '';
    const dados =
      this.conduta === 'agendamento'
        ? {
            conduta: this.conduta,
            justificativa: this.justificativa.trim(),
            data_agendamento: this.data.replace('T', ' ') + ':00',
            local: this.local.trim(),
          }
        : { conduta: this.conduta, justificativa: this.justificativa.trim() };
    this.api.regular(id, dados).subscribe({
      next: () => {
        this.enviando = false;
        this.detalhe = null;
        this.aviso = `Solicitação #${id} finalizada.`;
        this.carregar();
      },
      error: (e: unknown) => {
        this.enviando = false;
        this.erroModal = mensagemDeErro(e);
      },
    });
  }
}
