import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  
  let modifiedReq = req;
  if (req.url.startsWith('/api/')) {
    let customApiUrl = typeof window !== 'undefined' ? localStorage.getItem('BACKEND_API_URL') : null;
    
    // Auto-detect production mode and fallback to active localtunnel
    if (!customApiUrl && typeof window !== 'undefined' && 
        !window.location.hostname.includes('localhost') && 
        !window.location.hostname.includes('127.0.0.1')) {
      customApiUrl = 'https://tricky-radios-carry.loca.lt';
    }

    if (customApiUrl) {
      const base = customApiUrl.endsWith('/') ? customApiUrl.slice(0, -1) : customApiUrl;
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

