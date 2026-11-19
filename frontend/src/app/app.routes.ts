import { Routes } from '@angular/router';
import { raizGuard, perfilGuard } from './core/guards';
import { LoginComponent } from './pages/login.component';
import { SolicitacaoComponent } from './pages/solicitacao.component';
import { MinhasSolicitacoesComponent } from './pages/minhas-solicitacoes.component';
import { AvaliacaoParecerComponent } from './pages/avaliacao-parecer.component';
import { RegulacaoComponent } from './pages/regulacao.component';

export const routes: Routes = [
  { path: 'login', component: LoginComponent },
  { path: 'solicitacao', component: SolicitacaoComponent, canActivate: [perfilGuard('solicitante')] },
  { path: 'acompanhamento', component: MinhasSolicitacoesComponent, canActivate: [perfilGuard('solicitante')] },
  { path: 'avaliacao-parecer', component: AvaliacaoParecerComponent, canActivate: [perfilGuard('regulador')] },
  { path: 'regulacao', component: RegulacaoComponent, canActivate: [perfilGuard('nir')] },
  { path: '', pathMatch: 'full', canActivate: [raizGuard], children: [] },
  { path: '**', redirectTo: '' },
];
