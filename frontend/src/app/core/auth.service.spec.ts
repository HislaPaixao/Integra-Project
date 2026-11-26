import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import {
  provideHttpClientTesting,
  HttpTestingController
} from '@angular/common/http/testing';
import { Router } from '@angular/router';
import { AuthService } from './auth.service';

describe('AuthService', () => {

  let service: AuthService;
  let httpMock: HttpTestingController;
  let routerMock: jasmine.SpyObj<Router>;

  // Prepara o ambiente antes de cada teste.
  beforeEach(() => {

    // Remove os dados de autenticacao anteriores.
    localStorage.removeItem('integra_token');
    localStorage.removeItem('integra_usuario');

    // Simula o sistema de navegacao do Angular.
    routerMock = jasmine.createSpyObj('Router', ['navigate']);
    routerMock.navigate.and.returnValue(Promise.resolve(true));

    // Configura as dependencias utilizadas nos testes.
    TestBed.configureTestingModule({
      providers: [
        AuthService,
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: Router, useValue: routerMock }
      ]
    });

    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  // Verifica requisicoes pendentes e limpa a sessao.
  afterEach(() => {
    httpMock.verify();
    localStorage.removeItem('integra_token');
    localStorage.removeItem('integra_usuario');
  });

  // Teste 1: verifica se o usuario inicia deslogado.
  it('deve iniciar sem usuario autenticado', () => {

    expect(service.usuario()).toBeNull();
    expect(service.token()).toBeNull();
    expect(service.logado()).toBeFalse();

  });

  // Teste 2: verifica o login e o armazenamento da sessao.
  it('deve realizar login e armazenar o token', () => {

    const resposta = {
      token: 'token-teste',
      usuario: {
        id: 1,
        nome: 'Usuario Teste',
        email: 'teste@exemplo.com',
        perfil: 'regulador'
      }
    };

    service.login('teste@exemplo.com', 'senha-teste')
      .subscribe();

    // Intercepta a requisicao sem acessar o backend.
    const req = httpMock.expectOne('/api/auth/login');

    expect(req.request.method).toBe('POST');

    expect(req.request.body).toEqual({
      email: 'teste@exemplo.com',
      password: 'senha-teste'
    });

    // Simula uma resposta de autenticacao bem-sucedida.
    req.flush(resposta);

    expect(service.token()).toBe('token-teste');
    expect(service.usuario()?.email).toBe('teste@exemplo.com');
    expect(service.logado()).toBeTrue();

    expect(localStorage.getItem('integra_token'))
      .toBe('token-teste');

    expect(localStorage.getItem('integra_usuario'))
      .toBe(JSON.stringify(resposta.usuario));

  });

  // Teste 3: verifica o encerramento da sessao.
  it('deve realizar logout e limpar a sessao', () => {

    localStorage.setItem('integra_token', 'token-teste');
    localStorage.setItem('integra_usuario', '{}');

    service.logout();

    const req = httpMock.expectOne('/api/auth/logout');

    expect(req.request.method).toBe('POST');

    // Simula a resposta do servidor.
    req.flush({});

    expect(service.token()).toBeNull();
    expect(service.usuario()).toBeNull();
    expect(service.logado()).toBeFalse();

    expect(localStorage.getItem('integra_usuario'))
      .toBeNull();

    expect(routerMock.navigate)
      .toHaveBeenCalledWith(['/login']);

  });

  // Teste 4: verifica a identificacao do perfil.
  it('deve retornar o perfil do usuario autenticado', () => {

    service.login('teste@exemplo.com', 'senha-teste')
      .subscribe();

    const req = httpMock.expectOne('/api/auth/login');

    req.flush({
      token: 'token-teste',
      usuario: {
        id: 1,
        nome: 'Usuario Teste',
        email: 'teste@exemplo.com',
        perfil: 'regulador'
      }
    });

    expect(service.perfil()).toBe('regulador');

  });

  // Teste 5: verifica a pagina inicial de cada perfil.
  it('deve retornar a rota correta para cada perfil', () => {

    expect(service.homePorPerfil('regulador'))
      .toBe('/avaliacao-parecer');

    expect(service.homePorPerfil('nir'))
      .toBe('/regulacao');

    expect(service.homePorPerfil(null))
      .toBe('/solicitacao');

  });

});
