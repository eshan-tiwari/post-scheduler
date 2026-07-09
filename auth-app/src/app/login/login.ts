import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router, RouterLink } from '@angular/router';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login {
  loginForm: FormGroup;
  isLoading = false;
  errorMessage = '';
  successMessage = '';

  private readonly API_URL = '/api/login';

  constructor(
    private fb: FormBuilder,
    private http: HttpClient,
    private router: Router
  ) {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(6)]],
    });
  }

  onSubmit() {
    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      return;
    }

    this.isLoading = true;
    this.errorMessage = '';
    this.successMessage = '';

    const payload = this.loginForm.value;

    console.log('[Login] Sending POST to:', this.API_URL);
    console.log('[Login] Payload:', payload);

    this.http.post<any>(this.API_URL, payload).subscribe({
      next: (response) => {
        console.log('[Login] ✅ Success response:', response);
        this.isLoading = false;
        this.successMessage = 'Login successful! Redirecting to dashboard...';

        if (response.token) {
          localStorage.setItem('auth_token', response.token);
        }
        if (response.user) {
          localStorage.setItem('user', JSON.stringify(response.user));
        }

        this.loginForm.reset();
        setTimeout(() => this.router.navigate(['/dashboard']), 1000);
      },
      error: (error) => {
        console.error('[Login] ❌ Error response:', error);
        this.isLoading = false; // ALWAYS stop loading on error

        if (error.status === 0) {
          this.errorMessage = '❌ Cannot connect to server. Please ensure Laravel is running on http://localhost:8000';
        } else if (error.status === 401) {
          this.errorMessage = 'Invalid email or password.';
        } else if (error.status === 422) {
          if (error.error?.errors) {
            const key = Object.keys(error.error.errors)[0];
            this.errorMessage = error.error.errors[key][0];
          } else {
            this.errorMessage = error.error?.message || 'Validation failed.';
          }
        } else {
          this.errorMessage = error.error?.message || `❌ Login failed (HTTP ${error.status}).`;
        }
      },
    });
  }
}
