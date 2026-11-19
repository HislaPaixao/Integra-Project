import { DatePipe } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService, mensagemDeErro } from '../core/api.service';
import { Solicitacao } from '../core/models';

@Component({
  selector: 'app-minhas-solicitacoes',
  standalone: true,
  imports: [FormsModule, DatePipe],
  template: `
    <div class="card">
      <div class="card-header"><h2>Acompanhamento das solicitações</h2></div>
      <div class="card-body">
        @if (erro) { <div class="alerta alerta-erro">{{ erro }}</div> }
        @if (aviso) { <div class="alerta alerta-ok">{{ aviso }}</div> }
        <table class="tabela">
          <thead><tr><th>#</th><th>Data</th><th>Paciente</th><th>Clínica</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @for (s of lista; track s.id) {
              <tr>
                <td>{{ s.id }}</td>
                <td>{{ s.dataSolicitacao | date: 'dd/MM/yyyy' }}</td>
                <td>{{ s.paciente?.nome }}</td>
                <td>{{ s.especialidade }}</td>
                <td><span [class]="'selo ' + classe(s.status)">{{ s.status }}</span></td>
                <td><button class="btn btn-secundario" type="button" (click)="abrir(s.id)">Detalhes</button></td>
              </tr>
            } @empty {
              <tr><td colspan="6" class="vazio">Nenhuma solicitação encontrada.</td></tr>
            }
          </tbody>
        </table>
      </div>
    </div>

    @if (detalhe; as d) {
      <div class="modal-fundo" (click)="detalhe = null">
        <div class="modal" (click)="$event.stopPropagation()">
          <h3>Solicitação #{{ d.id }} — {{ d.paciente?.nome }}</h3>
          <p><strong>Clínica:</strong> {{ d.especialidade }} · <strong>Status:</strong> {{ d.status }}
            @if (d.prioridade) { · <strong>Prioridade:</strong> {{ d.prioridade }} }</p>
          <p class="texto">{{ d.descricaoClinica }}</p>
          @if (d.agendamento; as a) {
            <div class="alerta alerta-ok">Agendado para {{ a.data | date: 'dd/MM/yyyy HH:mm' }} — {{ a.local }}</div>
          }
          <h4>Histórico</h4>
          <ul class="historico">
            @for (h of d.historico ?? []; track $index) {
              <li><strong>{{ h.acao }}</strong> · {{ h.dataHora | date: 'dd/MM/yyyy HH:mm' }}<br />{{ h.descricao }}</li>
            }
          </ul>
          @if (d.status === 'Pendente') {
            <div class="form-group">
              <label for="compl">Informações complementares *</label>
              <textarea id="compl" name="compl" rows="4" [(ngModel)]="complemento" placeholder="Responda ao pedido do regulador"></textarea>
            </div>
            @if (erroModal) { <div class="alerta alerta-erro">{{ erroModal }}</div> }
            <button class="btn btn-primario" type="button" [disabled]="complemento.trim().length < 10 || enviando" (click)="enviarComplemento(d.id)">
              Enviar informações
            </button>
          }
          <button class="btn btn-secundario" type="button" (click)="detalhe = null">Fechar</button>
        </div>
      </div>
    }
  `,
})
export class MinhasSolicitacoesComponent implements OnInit {
  private readonly api = inject(ApiService);

  lista: Solicitacao[] = [];
  detalhe: Solicitacao | null = null;
  complemento = '';
  erro = '';
  erroModal = '';
  aviso = '';
  enviando = false;

  ngOnInit(): void {
    this.carregar();
  }

  carregar(): void {
    this.api.listar().subscribe({
      next: (l) => (this.lista = l),
      error: (e: unknown) => (this.erro = mensagemDeErro(e)),
    });
  }

  classe(status: string): string {
    return 'st-' + status.toLowerCase().replace(/\s+/g, '-');
  }

  abrir(id: number): void {
    this.complemento = '';
    this.erroModal = '';
    this.api.detalhar(id).subscribe({
      next: (d) => (this.detalhe = d),
      error: (e: unknown) => (this.erro = mensagemDeErro(e)),
    });
  }

  enviarComplemento(id: number): void {
    this.enviando = true;
    this.erroModal = '';
    this.api.complementar(id, this.complemento.trim()).subscribe({
      next: () => {
        this.enviando = false;
        this.detalhe = null;
        this.aviso = `Informações da solicitação #${id} enviadas. Ela voltou para a triagem.`;
        this.carregar();
      },
      error: (e: unknown) => {
        this.enviando = false;
        this.erroModal = mensagemDeErro(e);
      },
    });
  }
}
