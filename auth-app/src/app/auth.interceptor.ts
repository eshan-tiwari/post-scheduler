import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  
  let modifiedReq = req;
  if (req.url.startsWith('/api/')) {
    // 1. User-saved URL takes priority
    let backendUrl = typeof window !== 'undefined' ? localStorage.getItem('BACKEND_API_URL') : null;

    // 2. If no custom URL and we're on the deployed site (not localhost), use the fixed tunnel
    if (!backendUrl && typeof window !== 'undefined' &&
        !window.location.hostname.includes('localhost') &&
        !window.location.hostname.includes('127.0.0.1')) {
      backendUrl = 'https://laravel-backend-zeta.vercel.app';
    }

    if (backendUrl) {
      const base = backendUrl.endsWith('/') ? backendUrl.slice(0, -1) : backendUrl;
      modifiedReq = req.clone({
        url: base + req.url,
        setHeaders: {
          'Bypass-Tunnel-Reminder': 'true',
          'ngrok-skip-browser-warning': 'true'
        }
      });
    }
  }



  return next(modifiedReq).pipe(
    catchError((err) => {
      if (err.status === 401) {
        localStorage.removeItem('auth_token');
        router.navigate(['/login']);
      }
      return throwError(() => err);
    })
  );
};

