import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { environment } from '../../../environments/environment';
import { installFakeLocalStorage } from '../testing/fake-local-storage';
import { authInterceptor } from './auth.interceptor';

describe('authInterceptor', () => {
  let http: HttpClient;
  let httpMock: HttpTestingController;
  let router: Router;

  beforeEach(() => {
    installFakeLocalStorage();
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        provideRouter([]),
      ],
    });
    http = TestBed.inject(HttpClient);
    httpMock = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
  });

  afterEach(() => {
    httpMock.verify();
    vi.unstubAllGlobals();
  });

  it('attaches the bearer token to API requests when authenticated', () => {
    localStorage.setItem('devaudit_token', 'my-token');

    http.get(`${environment.apiUrl}/repositories`).subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/repositories`);
    expect(req.request.headers.get('Authorization')).toBe('Bearer my-token');
    req.flush([]);
  });

  it('does not attach a header when unauthenticated', () => {
    http.get(`${environment.apiUrl}/repositories`).subscribe();

    const req = httpMock.expectOne(`${environment.apiUrl}/repositories`);
    expect(req.request.headers.has('Authorization')).toBe(false);
    req.flush([]);
  });

  it('logs out and redirects to /login on a 401 from a protected endpoint', () => {
    localStorage.setItem('devaudit_token', 'my-token');
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    http.get(`${environment.apiUrl}/repositories`).subscribe({ error: () => {} });

    httpMock.expectOne(`${environment.apiUrl}/repositories`).flush({}, { status: 401, statusText: 'Unauthorized' });

    expect(localStorage.getItem('devaudit_token')).toBeNull();
    expect(navigateSpy).toHaveBeenCalledWith(['/login'], expect.any(Object));
  });

  it('does not redirect on a 401 from the login endpoint itself', () => {
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    http.post(`${environment.apiUrl}/login`, {}).subscribe({ error: () => {} });

    httpMock.expectOne(`${environment.apiUrl}/login`).flush({}, { status: 401, statusText: 'Unauthorized' });

    expect(navigateSpy).not.toHaveBeenCalled();
  });
});
