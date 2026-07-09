import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  
  let modifiedReq = req;
  if (req.url.startsWith('/api/')) {
    const customApiUrl = typeof window !== 'undefined' ? localStorage.getItem('BACKEND_API_URL') : null;
    if (customApiUrl) {
      const base = customApiUrl.endsWith('/') ? customApiUrl.slice(0, -1) : customApiUrl;
      modifiedReq = req.clone({ url: base + req.url });
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

