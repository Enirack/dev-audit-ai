import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/guards/auth.guard';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    loadComponent: () => import('./features/landing/landing').then((m) => m.Landing),
  },
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/auth/login/login').then((m) => m.Login),
  },
  {
    path: 'register',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/auth/register/register').then((m) => m.Register),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./layout/app-shell/app-shell').then((m) => m.AppShell),
    children: [
      {
        path: 'dashboard',
        loadComponent: () => import('./features/dashboard/dashboard').then((m) => m.Dashboard),
      },
      {
        path: 'repositories/:id',
        loadComponent: () =>
          import('./features/repository-detail/repository-detail').then((m) => m.RepositoryDetail),
      },
      {
        path: 'audits/:id',
        loadComponent: () => import('./features/audit-overview/audit-overview').then((m) => m.AuditOverview),
      },
      {
        path: 'audits/:id/findings',
        loadComponent: () =>
          import('./features/findings/findings-list/findings-list').then((m) => m.FindingsList),
      },
      {
        path: 'audits/:id/findings/:findingId',
        loadComponent: () =>
          import('./features/findings/finding-detail/finding-detail').then((m) => m.FindingDetail),
      },
      {
        path: 'audits/:id/assistant',
        loadComponent: () => import('./features/ai-assistant/ai-assistant').then((m) => m.AiAssistant),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
