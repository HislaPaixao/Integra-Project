import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../core/auth.service';
import { mensagemDeErro } from '../core/api.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [FormsModule],
  template: `
    <div class="card card-login">
      <div class="card-header"><h2>Entrar</h2></div>
      <form class="card-body" (ngSubmit)="entrar()">
        <div class="form-group">
          <label for="email">E-mail</label>
          <input id="email" name="email" type="email" [(ngModel)]="email" required autocomplete="username" />
        </div>
        <div class="form-group">
          <label for="senha">Senha</label>
          <input id="senha" name="senha" type="password" [(ngModel)]="senha" required autocomplete="current-password" />
        </div>
        @if (erro) {
          <div class="alerta alerta-erro">{{ erro }}</div>
        }
        <button class="btn btn-primario" type="submit" [disabled]="carregando || !email || !senha">
          {{ carregando ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>
    </div>
  `,
})
export class LoginComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  email = '';
  senha = '';
  erro = '';
  carregando = false;

  entrar(): void {
    this.erro = '';
    this.carregando = true;
    this.auth.login(this.email, this.senha).subscribe({
      next: (resp) => {
        this.carregando = false;
        void this.router.navigate([this.auth.homePorPerfil(resp.usuario.perfil)]);
      },
      error: (e: unknown) => {
        this.carregando = false;
        this.erro = mensagemDeErro(e, 'E-mail ou senha incorretos.');
      },
    });
  }
}
