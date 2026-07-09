import { RenderMode, ServerRoute } from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  // Auth pages - must be Client-Side Rendered only
  // (they make HTTP calls to Laravel; SSR prerender would hang without Laravel running)
  { path: 'login',            renderMode: RenderMode.Client },
  { path: 'register',         renderMode: RenderMode.Client },
  { path: 'dashboard',        renderMode: RenderMode.Client },
  { path: 'create-post',      renderMode: RenderMode.Client },
  { path: 'scheduled-posts',  renderMode: RenderMode.Client },

  // Catch-all fallback
  { path: '**', renderMode: RenderMode.Client },
];
