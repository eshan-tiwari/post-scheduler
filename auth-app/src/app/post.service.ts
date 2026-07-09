import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface ScheduledPost {
  id?: number;
  title: string;
  content: string;
  platform: string;
  platforms?: string[];
  scheduled_at: string;
  timezone?: string;
  recurrence?: string;
  status?: string;
  failed_reason?: string;
  error_message?: string;
  media?: any[];
  schedules?: any[];
  created_at?: string;
  updated_at?: string;
}


export interface ConnectedAccount {
  id: number;
  user_id: number;
  platform: string;
  platform_user_id: string;
  username: string;
  avatar_url?: string;
  created_at?: string;
  updated_at?: string;
}

@Injectable({ providedIn: 'root' })
export class PostService {
  private apiUrl = '/api';

  constructor(private http: HttpClient) {}

  private getHeaders(): HttpHeaders {
    const token = typeof window !== 'undefined' ? (localStorage.getItem('auth_token') ?? '') : '';
    return new HttpHeaders({
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`
    });
  }

  // Scheduled Posts CRUD
  getPosts(params?: { status?: string; search?: string; page?: number; per_page?: number }): Observable<any> {
    const headers = this.getHeaders();
    let url = `${this.apiUrl}/posts`;
    const queryParams: string[] = [];
    
    if (params?.status) queryParams.push(`status=${params.status}`);
    if (params?.search) queryParams.push(`search=${params.search}`);
    if (params?.page) queryParams.push(`page=${params.page}`);
    if (params?.per_page) queryParams.push(`per_page=${params.per_page}`);
    
    if (queryParams.length > 0) {
      url += `?${queryParams.join('&')}`;
    }

    return this.http.get<any>(url, { headers });
  }

  createPost(payload: any): Observable<any> {
    const headers = this.getHeaders();
    // Use FormData if media files are attached
    if (payload instanceof FormData) {
      return this.http.post(`${this.apiUrl}/posts`, payload, { headers });
    }
    return this.http.post(`${this.apiUrl}/posts`, payload, {
      headers: headers.set('Content-Type', 'application/json')
    });
  }

  updatePost(id: number, post: Partial<ScheduledPost>): Observable<any> {
    return this.http.put(`${this.apiUrl}/posts/${id}`, post, {
      headers: this.getHeaders().set('Content-Type', 'application/json')
    });
  }

  deletePost(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/posts/${id}`, {
      headers: this.getHeaders()
    });
  }

  publishPost(id: number): Observable<any> {
    return this.http.put(
      `${this.apiUrl}/posts/${id}`,
      { status: 'Published' },
      { headers: this.getHeaders().set('Content-Type', 'application/json') }
    );
  }

  retryPost(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/posts/${id}/retry`, {}, {
      headers: this.getHeaders()
    });
  }

  // Social Account APIs
  getSocialAccounts(): Observable<any> {
    return this.http.get(`${this.apiUrl}/social/accounts`, {
      headers: this.getHeaders()
    });
  }

  connectSocialAccount(platform: string): Observable<any> {
    return this.http.get(`${this.apiUrl}/social/connect/${platform}`, {
      headers: this.getHeaders()
    });
  }

  disconnectSocialAccount(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/social/accounts/${id}`, {
      headers: this.getHeaders()
    });
  }

  // Dashboard Stats API
  getDashboardStats(): Observable<any> {
    return this.http.get(`${this.apiUrl}/dashboard/stats`, {
      headers: this.getHeaders()
    });
  }

  // Publish Logs API
  getPublishLogs(): Observable<any> {
    return this.http.get(`${this.apiUrl}/publish-logs`, {
      headers: this.getHeaders()
    });
  }
}
