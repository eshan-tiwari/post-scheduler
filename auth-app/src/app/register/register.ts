import { Component } from '@angular/core';
import {
  FormBuilder,
  FormGroup,
  Validators,
  ReactiveFormsModule,
  AbstractControl,
  ValidationErrors
} from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router, RouterLink } from '@angular/router';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './register.html',
  styleUrl: './register.css',
})
export class Register {
  registerForm: FormGroup;
  isLoading = false;
  errorMessage = '';
  successMessage = '';

  // Laravel API endpoint
  private readonly API_URL = '/api/register';

  constructor(
    private fb: FormBuilder,
    private http: HttpClient,
    private router: Router
  ) {
    this.registerForm = this.fb.group(
      {
        name: ['', [Validators.required, Validators.minLength(2)]],
        email: ['', [Validators.required, Validators.email]],
        password: ['', [Validators.required, Validators.minLength(6)]],
        confirmPassword: ['', [Validators.required]],
      },
      { validators: this.passwordMatchValidator }
    );
  }

  passwordMatchValidator(control: AbstractControl): ValidationErrors | null {
    const password = control.get('password');
    const confirmPassword = control.get('confirmPassword');

    if (!password || !confirmPassword) return null;

    if (password.value !== confirmPassword.value) {
      confirmPassword.setErrors({ passwordMismatch: true });
      return { passwordMismatch: true };
    } else {
      // Clear passwordMismatch error only — keep other errors intact
      const errors = confirmPassword.errors;
      if (errors && errors['passwordMismatch']) {
        const { passwordMismatch, ...rest } = errors;
        confirmPassword.setErrors(Object.keys(rest).length ? rest : null);
      }
      return null;
    }
  }

  onSubmit() {
    if (this.registerForm.invalid) {
      this.registerForm.markAllAsTouched();
      console.warn('[Register] Form is invalid — submission blocked.');
      return;
    }

    this.isLoading = true;
    this.errorMessage = '';
    this.successMessage = '';

    // Do NOT send confirmPassword to Laravel — it doesn't expect it
    const { name, email, password } = this.registerForm.value;
    const payload = { name, email, password };

    console.log('[Register] Sending POST to:', this.API_URL);
    console.log('[Register] Payload:', payload);

    this.http.post<any>(this.API_URL, payload).subscribe({
      next: (response) => {
        console.log('[Register] ✅ Success response:', response);
        this.isLoading = false;
        this.successMessage = 'Registration successful! Redirecting to login...';
        this.registerForm.reset();

        setTimeout(() => {
          this.router.navigate(['/login']);
        }, 2000);
      },
      error: (error) => {
        console.error('[Register] ❌ Error response:', error);
        this.isLoading = false; // ALWAYS stop loading on error

        if (error.status === 0) {
          // Network error — Laravel not running
          this.errorMessage =
            '❌ Cannot connect to server. Please ensure Laravel is running on http://localhost:8000';
        } else if (error.status === 422) {
          // Laravel validation failed
          if (error.error?.errors) {
            const firstKey = Object.keys(error.error.errors)[0];
            this.errorMessage = error.error.errors[firstKey][0];
          } else {
            this.errorMessage = error.error?.message || 'Validation failed.';
          }
        } else if (error.status === 500) {
          this.errorMessage = '❌ Server error (500). Check Laravel logs.';
        } else {
          this.errorMessage = error.error?.message || `❌ Registration failed (HTTP ${error.status}).`;
        }
      },
    });
  }
}
