import { Routes } from '@angular/router';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { Login } from './login/login';
import { Register } from './register/register';
import { Dashboard } from './dashboard/dashboard';
import { CreatePost } from './create-post/create-post';
import { ScheduledPosts } from './scheduled-posts/scheduled-posts';
import { SocialAccounts } from './social-accounts/social-accounts';
import { ConnectPlatforms } from './connect-platforms/connect-platforms';

function authGuard() {
  const router = inject(Router);
  const token = localStorage.getItem('auth_token');
  if (!token) {
    router.navigate(['/login']);
    return false;
  }
  return true;
}

export const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: 'login', component: Login },
  { path: 'register', component: Register },
  { path: 'dashboard', component: Dashboard, canActivate: [authGuard] },
  { path: 'create-post', component: CreatePost, canActivate: [authGuard] },
  { path: 'scheduled-posts', component: ScheduledPosts, canActivate: [authGuard] },
  { path: 'social-accounts', component: SocialAccounts, canActivate: [authGuard] },
  { path: 'connect-platforms', component: ConnectPlatforms, canActivate: [authGuard] },
  { path: '**', redirectTo: 'login' }
];

