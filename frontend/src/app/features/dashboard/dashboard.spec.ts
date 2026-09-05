import { TestBed } from '@angular/core/testing';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { Dashboard } from './dashboard';
import { environment } from '../../../environments/environment';

describe('Dashboard', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Dashboard],
      providers: [provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();

    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('shows the backend status once the health check resolves', () => {
    const fixture = TestBed.createComponent(Dashboard);
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/health`);
    req.flush({ status: 'ok' });
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('.status--ok')?.textContent).toContain('ok');
  });

  it('shows an error state when the health check fails', () => {
    const fixture = TestBed.createComponent(Dashboard);
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/health`);
    req.error(new ProgressEvent('error'));
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('.status--error')).toBeTruthy();
  });
});
