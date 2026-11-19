import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from './core/auth.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <div class="layout">
      <header class="navbar">
        <div class="logo">
          <h1>Integra / NIR</h1>
          <span>Módulo de Consultoria Ambulatorial</span>
        </div>
        @if (auth.usuario(); as u) {
          <div class="usuario">
            <div>
              <strong>{{ u.nome }}</strong>
              <small>{{ rotuloPerfil(u.perfil) }}@if (u.crm) { · CRM {{ u.crm }} }</small>
            </div>
            <button type="button" class="btn btn-secundario" (click)="auth.logout()">Sair</button>
          </div>
        }
      </header>

      @if (auth.usuario(); as u) {
        <nav class="menu">
          @if (u.perfil === 'solicitante') {
            <a routerLink="/solicitacao" routerLinkActive="ativo">Nova Solicitação</a>
            <a routerLink="/acompanhamento" routerLinkActive="ativo">Acompanhamento</a>
          }
          @if (u.perfil === 'regulador') {
            <a routerLink="/avaliacao-parecer" routerLinkActive="ativo">Avaliação de Parecer</a>
          }
          @if (u.perfil === 'nir') {
            <a routerLink="/regulacao" routerLinkActive="ativo">Regulação NIR</a>
          }
        </nav>
      }

      <main class="content">
        <router-outlet />
      </main>
    </div>
  `,
})
export class AppComponent {
  readonly auth = inject(AuthService);
  rotuloPerfil(perfil: string): string {
    return perfil === 'solicitante' ? 'Médico solicitante' : perfil === 'regulador' ? 'Médico regulador' : 'NIR';
  }
}
