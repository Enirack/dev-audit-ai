import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-register',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './register.html',
  styleUrl: '../auth.scss',
})
export class Register {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly submitting = signal(false);
  protected readonly errorMessage = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]],
  });

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.errorMessage.set(null);
    this.submitting.set(true);
    const credentials = this.form.getRawValue();

    this.auth.register(credentials).subscribe({
      next: () => {
        this.auth.login(credentials).subscribe({
          next: () => {
            this.auth.loadCurrentUser().subscribe();
            this.router.navigateByUrl('/dashboard');
          },
          error: () => {
            this.submitting.set(false);
            this.router.navigateByUrl('/login');
          },
        });
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        if (error.status === 409) {
          this.errorMessage.set('An account with this email already exists.');
        } else if (error.status === 422) {
          this.errorMessage.set('Password must be at least 8 characters long.');
        } else {
          this.errorMessage.set('Unable to create your account right now. Please try again.');
        }
      },
    });
  }
}
