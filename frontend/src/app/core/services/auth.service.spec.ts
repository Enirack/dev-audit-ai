import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { installFakeLocalStorage } from '../testing/fake-local-storage';
import { AuthService } from './auth.service';

describe('AuthService', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    installFakeLocalStorage();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
    vi.unstubAllGlobals();
  });

  it('starts unauthenticated when no token is stored', () => {
    expect(service.isAuthenticated()).toBe(false);
  });

  it('stores the token and becomes authenticated after login', () => {
    service.login({ email: 'a@b.com', password: 'secret123' }).subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/login`);
    req.flush({ token: 'abc.def.ghi' });

    expect(service.isAuthenticated()).toBe(true);
    expect(service.token()).toBe('abc.def.ghi');
    expect(localStorage.getItem('devaudit_token')).toBe('abc.def.ghi');
  });

  it('sets the current user after loadCurrentUser resolves', () => {
    service.loadCurrentUser().subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/me`);
    req.flush({ id: '1', email: 'a@b.com', createdAt: '2026-01-01T00:00:00Z' });

    expect(service.user()?.email).toBe('a@b.com');
  });

  it('clears token and user on logout', () => {
    service.login({ email: 'a@b.com', password: 'secret123' }).subscribe();
    httpMock.expectOne(`${environment.apiUrl}/login`).flush({ token: 'abc.def.ghi' });

    service.logout();

    expect(service.isAuthenticated()).toBe(false);
    expect(service.user()).toBeNull();
    expect(localStorage.getItem('devaudit_token')).toBeNull();
  });

  it('rehydrates the token from localStorage for a fresh service instance', () => {
    localStorage.setItem('devaudit_token', 'stored-token');

    const rehydrated = TestBed.runInInjectionContext(() => new AuthService());

    expect(rehydrated.isAuthenticated()).toBe(true);
    expect(rehydrated.token()).toBe('stored-token');
  });
});
