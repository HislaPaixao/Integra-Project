import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';

/** Envia o token em toda chamada /api e derruba a sessão se o servidor responder 401. */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const token = auth.token();
  const requisicao = token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;

  return next(requisicao).pipe(
    catchError((erro: unknown) => {
      if (erro instanceof HttpErrorResponse && erro.status === 401 && !req.url.endsWith('/auth/login')) {
        auth.encerrarSessao();
      }
      return throwError(() => erro);
    }),
  );
};
