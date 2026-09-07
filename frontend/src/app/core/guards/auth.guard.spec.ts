import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, UrlTree, provideRouter } from '@angular/router';
import { installFakeLocalStorage } from '../testing/fake-local-storage';
import { authGuard, guestGuard } from './auth.guard';

describe('authGuard / guestGuard', () => {
  beforeEach(() => {
    installFakeLocalStorage();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
  });

  afterEach(() => vi.unstubAllGlobals());

  function runAuthGuard() {
    return TestBed.runInInjectionContext(() =>
      authGuard({} as never, { url: '/dashboard' } as never),
    );
  }

  function runGuestGuard() {
    return TestBed.runInInjectionContext(() => guestGuard({} as never, {} as never));
  }

  it('authGuard blocks unauthenticated users and redirects to /login with a returnUrl', () => {
    const router = TestBed.inject(Router);
    const result = runAuthGuard();

    expect(result).toBeInstanceOf(UrlTree);
    expect(router.serializeUrl(result as UrlTree)).toBe('/login?returnUrl=%2Fdashboard');
  });

  it('authGuard allows authenticated users through', () => {
    localStorage.setItem('devaudit_token', 'token');

    expect(runAuthGuard()).toBe(true);
  });

  it('guestGuard allows unauthenticated users through', () => {
    expect(runGuestGuard()).toBe(true);
  });

  it('guestGuard redirects authenticated users to /dashboard', () => {
    localStorage.setItem('devaudit_token', 'token');
    const router = TestBed.inject(Router);

    const result = runGuestGuard();

    expect(result).toBeInstanceOf(UrlTree);
    expect(router.serializeUrl(result as UrlTree)).toBe('/dashboard');
  });
});
