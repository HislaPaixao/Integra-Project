import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { LoginComponent } from './login.component';
import { AuthService } from '../core/auth.service';

describe('LoginComponent', () => {

  let component: LoginComponent;
  let fixture: ComponentFixture<LoginComponent>;
  let authMock: jasmine.SpyObj<AuthService>;
  let routerMock: jasmine.SpyObj<Router>;

  // Prepara o ambiente antes de cada teste.
  beforeEach(async () => {

    // Simula o servico de autenticacao.
    authMock = jasmine.createSpyObj('AuthService', [
      'login',
      'homePorPerfil'
    ]);

    // Simula o sistema de navegacao do Angular.
    routerMock = jasmine.createSpyObj('Router', ['navigate']);
    routerMock.navigate.and.returnValue(Promise.resolve(true));

    // Configura o componente e suas dependencias.
    await TestBed.configureTestingModule({
      imports: [LoginComponent],
      providers: [
        { provide: AuthService, useValue: authMock },
        { provide: Router, useValue: routerMock }
      ]
    }).compileComponents();

    // Cria uma instancia do componente.
    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;

    // Atualiza a interface do componente.
    fixture.detectChanges();

  });

  // Teste 1: verifica o formulario com campos vazios.
  it('deve desabilitar o botao quando os campos estiverem vazios', () => {

    expect(component.email).toBe('');
    expect(component.senha).toBe('');

    // Localiza o botao de login na interface.
    const botao: HTMLButtonElement =
      fixture.nativeElement.querySelector('button');

    // Verifica se o botao esta desabilitado.
    expect(botao.disabled).toBeTrue();

    // Verifica se nenhuma autenticacao foi solicitada.
    expect(authMock.login).not.toHaveBeenCalled();

  });

  // Teste 2: verifica uma autenticacao bem-sucedida.
  it('deve realizar login e redirecionar o usuario', () => {

    // Preenche os dados do formulario.
    component.email = 'teste@exemplo.com';
    component.senha = 'senha-teste';

    // Simula uma resposta positiva da autenticacao.
    authMock.login.and.returnValue(of({
      token: 'token-teste',
      usuario: {
        id: 1,
        nome: 'Usuario Teste',
        email: 'teste@exemplo.com',
        perfil: 'regulador'
      }
    } as any));

    // Define a rota correspondente ao perfil.
    authMock.homePorPerfil.and.returnValue('/avaliacao-parecer');

    // Executa a funcao de login.
    component.entrar();

    // Verifica os dados enviados ao servico.
    expect(authMock.login).toHaveBeenCalledWith(
      'teste@exemplo.com',
      'senha-teste'
    );

    // Verifica a identificacao do perfil.
    expect(authMock.homePorPerfil)
      .toHaveBeenCalledWith('regulador');

    // Verifica o redirecionamento.
    expect(routerMock.navigate)
      .toHaveBeenCalledWith(['/avaliacao-parecer']);

    expect(component.carregando).toBeFalse();
    expect(component.erro).toBe('');

  });

  // Teste 3: verifica uma tentativa de login malsucedida.
  it('deve exibir mensagem de erro quando o login falhar', () => {

    component.email = 'teste@exemplo.com';
    component.senha = 'senha-incorreta';

    // Simula uma falha de autenticacao.
    authMock.login.and.returnValue(
      throwError(() => ({
        status: 401,
        error: {}
      }))
    );

    // Executa a tentativa de login.
    component.entrar();

    // Verifica o encerramento do carregamento.
    expect(component.carregando).toBeFalse();

    // Verifica a mensagem de erro.
    expect(component.erro)
      .toBe('E-mail ou senha incorretos.');

    // Atualiza a interface.
    fixture.detectChanges();

    // Verifica se a mensagem aparece na tela.
    const alerta: HTMLElement =
      fixture.nativeElement.querySelector('.alerta-erro');

    expect(alerta.textContent)
      .toContain('E-mail ou senha incorretos.');

    // Impede o redirecionamento apos uma falha.
    expect(routerMock.navigate).not.toHaveBeenCalled();

  });

});
