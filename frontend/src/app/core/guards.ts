import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';
import { Perfil } from './models';

/** Rota raiz: manda para a página do perfil (ou para o login). */
export const raizGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  return router.createUrlTree([auth.logado() ? auth.homePorPerfil(auth.perfil()) : '/login']);
};

/** Libera a rota só para os perfis informados; os demais voltam para a própria página inicial. */
export function perfilGuard(...permitidos: Perfil[]): CanActivateFn {
  return () => {
    const auth = inject(AuthService);
    const router = inject(Router);
    if (!auth.logado()) {
      return router.createUrlTree(['/login']);
    }
    const perfil = auth.perfil();
    return perfil !== null && permitidos.includes(perfil)
      ? true
      : router.createUrlTree([auth.homePorPerfil(perfil)]);
  };
}
