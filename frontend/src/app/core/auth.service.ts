import { Injectable, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, catchError, of, tap } from 'rxjs';
import { LoginResposta, Perfil, Usuario } from './models';

const CHAVE_TOKEN = 'integra_token';
const CHAVE_USUARIO = 'integra_usuario';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  readonly usuario = signal<Usuario | null>(this.lerUsuario());
  readonly logado = computed(() => this.usuario() !== null && this.token() !== null);

  token(): string | null {
    try {
      return localStorage.getItem(CHAVE_TOKEN);
    } catch {
      return null;
    }
  }

  login(email: string, password: string): Observable<LoginResposta> {
    return this.http.post<LoginResposta>('/api/auth/login', { email, password }).pipe(
      tap((resp) => {
        try {
          localStorage.setItem(CHAVE_TOKEN, resp.token);
          localStorage.setItem(CHAVE_USUARIO, JSON.stringify(resp.usuario));
        } catch {
          /* armazenamento indisponível: a sessão vale só até recarregar */
        }
        this.usuario.set(resp.usuario);
      }),
    );
  }

  /** Revoga o token no servidor e limpa a sessão local. */
  logout(): void {
    this.http.post('/api/auth/logout', {}).pipe(catchError(() => of(null))).subscribe(() => this.encerrarSessao());
  }

  /** Limpa a sessão local (usado também quando o servidor responde 401). */
  encerrarSessao(): void {
    try {
      localStorage.removeItem(CHAVE_TOKEN);
      localStorage.removeItem(CHAVE_USUARIO);
    } catch {
      /* ignora */
    }
    this.usuario.set(null);
    void this.router.navigate(['/login']);
  }

  perfil(): Perfil | null {
    return this.usuario()?.perfil ?? null;
  }

  /** Página inicial de cada perfil. */
  homePorPerfil(perfil: Perfil | null): string {
    switch (perfil) {
      case 'regulador':
        return '/avaliacao-parecer';
      case 'nir':
        return '/regulacao';
      default:
        return '/solicitacao';
    }
  }

  private lerUsuario(): Usuario | null {
    try {
      const bruto = localStorage.getItem(CHAVE_USUARIO);
      return bruto ? (JSON.parse(bruto) as Usuario) : null;
    } catch {
      return null;
    }
  }
}
