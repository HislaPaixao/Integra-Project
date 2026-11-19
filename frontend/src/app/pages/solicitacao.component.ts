import { Component, OnDestroy, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Subject, Subscription } from 'rxjs';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { ApiService, mensagemDeErro } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { ESPECIALIDADES, PacienteAghu } from '../core/models';

@Component({
  selector: 'app-solicitacao',
  standalone: true,
  imports: [FormsModule],
  template: `
    <div class="card">
      <div class="card-header"><h2>Nova Solicitação de Consultoria</h2></div>
      <form class="card-body" (ngSubmit)="enviar()">
        <section class="form-section">
          <h3>Identificação do Paciente</h3>
          <div class="form-row">
            <div class="form-group">
              <label for="prontuario">Prontuário *</label>
              <input id="prontuario" name="prontuario" [ngModel]="prontuario" (ngModelChange)="aoDigitar($event)"
                     placeholder="Ex: 123456" autocomplete="off" />
              @if (buscando) { <small>Consultando AGHU...</small> }
              @if (paciente && !buscando) { <small class="ok">✔ Dados carregados do AGHU</small> }
            </div>
            <div class="form-group grande">
              <label for="nome">Nome do paciente</label>
              <input id="nome" name="nome" [value]="paciente?.nomePaciente ?? ''" readonly placeholder="Preenchido automaticamente" />
            </div>
          </div>
          @if (erroPaciente) {
            <div class="alerta alerta-erro">{{ erroPaciente }}</div>
          }
          <div class="form-row">
            <div class="form-group"><label>Cartão SUS</label><input [value]="paciente?.cartaoSus ?? ''" name="sus" readonly /></div>
            <div class="form-group"><label>CPF</label><input [value]="paciente?.cpf ?? ''" name="cpf" readonly /></div>
            <div class="form-group"><label>Leito</label><input [value]="paciente?.leito ?? ''" name="leito" readonly /></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>Nº da consulta</label><input [value]="paciente?.numeroConsulta ?? ''" name="consulta" readonly /></div>
            <div class="form-group grande"><label>Clínica de origem</label><input [value]="paciente?.clinicaOrigem ?? ''" name="origem" readonly /></div>
          </div>
        </section>

        <section class="form-section">
          <h3>Dados da Consultoria</h3>
          <div class="form-row">
            <div class="form-group">
              <label for="solicitante">Médico solicitante</label>
              <input id="solicitante" name="solicitante" [value]="auth.usuario()?.nome ?? ''" readonly />
            </div>
            <div class="form-group">
              <label for="crm">CRM</label>
              <input id="crm" name="crm" [value]="auth.usuario()?.crm ?? ''" readonly />
            </div>
            <div class="form-group">
              <label for="destino">Clínica destino *</label>
              <select id="destino" name="destino" [(ngModel)]="especialidade">
                <option value="" disabled>Selecione a clínica</option>
                @for (c of clinicas; track c) { <option [value]="c">{{ c }}</option> }
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="descricao">Descrição clínica * <small>(mín. 20 caracteres)</small></label>
            <textarea id="descricao" name="descricao" rows="6" [(ngModel)]="descricao"
                      placeholder="Descreva a situação clínica do paciente"></textarea>
          </div>
        </section>

        @if (erro) { <div class="alerta alerta-erro">{{ erro }}</div> }
        @if (sucesso) { <div class="alerta alerta-ok">{{ sucesso }}</div> }

        <div class="acoes">
          <button class="btn btn-primario" type="submit" [disabled]="enviando || !formularioValido()">
            {{ enviando ? 'Enviando...' : 'Enviar solicitação' }}
          </button>
          <button class="btn btn-secundario" type="button" (click)="limpar()">Limpar</button>
        </div>
      </form>
    </div>
  `,
})
export class SolicitacaoComponent implements OnDestroy {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);
  readonly auth = inject(AuthService);

  readonly clinicas = ESPECIALIDADES;
  prontuario = '';
  paciente: PacienteAghu | null = null;
  buscando = false;
  erroPaciente = '';
  especialidade = '';
  descricao = '';
  erro = '';
  sucesso = '';
  enviando = false;

  private readonly digitacao = new Subject<string>();
  private readonly inscricao: Subscription = this.digitacao
    .pipe(debounceTime(500), distinctUntilChanged())
    .subscribe((valor) => this.buscar(valor));

  ngOnDestroy(): void {
    this.inscricao.unsubscribe();
  }

  aoDigitar(valor: string): void {
    this.prontuario = valor;
    this.paciente = null;
    this.erroPaciente = '';
    this.digitacao.next(valor.trim());
  }

  private buscar(valor: string): void {
    if (valor.length < 3) {
      return;
    }
    this.buscando = true;
    this.api.buscarPaciente(valor).subscribe({
      next: (p) => {
        this.paciente = p;
        this.buscando = false;
      },
      error: (e: unknown) => {
        this.buscando = false;
        this.erroPaciente = mensagemDeErro(e, 'Paciente não encontrado no AGHU.');
      },
    });
  }

  formularioValido(): boolean {
    return this.paciente !== null && this.especialidade !== '' && this.descricao.trim().length >= 20;
  }

  enviar(): void {
    if (!this.paciente || !this.formularioValido()) {
      return;
    }
    this.erro = '';
    this.sucesso = '';
    this.enviando = true;
    this.api
      .criar({ id_paciente: this.paciente.idPaciente, especialidade: this.especialidade, descricao_clinica: this.descricao.trim() })
      .subscribe({
        next: (r) => {
          this.enviando = false;
          this.sucesso = `Solicitação #${r.id} enviada com sucesso! Status: ${r.status}.`;
          this.limpar(false);
          setTimeout(() => void this.router.navigate(['/acompanhamento']), 1500);
        },
        error: (e: unknown) => {
          this.enviando = false;
          this.erro = mensagemDeErro(e);
        },
      });
  }

  limpar(apagarMensagens = true): void {
    this.prontuario = '';
    this.paciente = null;
    this.especialidade = '';
    this.descricao = '';
    this.erroPaciente = '';
    if (apagarMensagens) {
      this.erro = '';
      this.sucesso = '';
    }
  }
}
