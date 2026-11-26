import {
  ComponentFixture,
  TestBed,
  fakeAsync,
  tick
} from '@angular/core/testing';
import { Router } from '@angular/router';
import { of } from 'rxjs';
import { SolicitacaoComponent } from './solicitacao.component';
import { ApiService } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { PacienteAghu } from '../core/models';

describe('SolicitacaoComponent', () => {

  let component: SolicitacaoComponent;
  let fixture: ComponentFixture<SolicitacaoComponent>;
  let apiMock: jasmine.SpyObj<ApiService>;
  let routerMock: jasmine.SpyObj<Router>;

  // Cria um paciente ficticio para os testes.
  const paciente = {
    idPaciente: 1,
    prontuario: '12345',
    nomePaciente: 'Paciente Teste',
    cartaoSus: '000000000000000',
    cpf: '00000000000',
    leito: '101',
    numeroConsulta: '100',
    clinicaOrigem: 'Cirurgia geral'
  } as PacienteAghu;

  // Prepara o ambiente antes de cada teste.
  beforeEach(async () => {

    // Simula o servico de comunicacao com o backend.
    apiMock = jasmine.createSpyObj('ApiService', [
      'buscarPaciente',
      'criar'
    ]);

    // Simula o sistema de navegacao.
    routerMock = jasmine.createSpyObj('Router', ['navigate']);
    routerMock.navigate.and.returnValue(Promise.resolve(true));

    // Simula o usuario autenticado.
    const authMock = {
      usuario: () => ({
        nome: 'Medico Teste',
        crm: '12345'
      })
    };

    // Configura o componente e suas dependencias.
    await TestBed.configureTestingModule({
      imports: [SolicitacaoComponent],
      providers: [
        { provide: ApiService, useValue: apiMock },
        { provide: AuthService, useValue: authMock },
        { provide: Router, useValue: routerMock }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(SolicitacaoComponent);
    component = fixture.componentInstance;

    fixture.detectChanges();

  });

  // Teste 1: verifica as regras de validacao do formulario.
  it('deve validar corretamente os campos obrigatorios', () => {

    // Formulario inicialmente vazio.
    expect(component.formularioValido()).toBeFalse();

    // Preenche os dados do paciente.
    component.paciente = paciente;

    // Ainda falta a especialidade e a descricao.
    expect(component.formularioValido()).toBeFalse();

    component.especialidade = 'Cirurgia geral';

    // Descricao com apenas 19 caracteres.
    component.descricao = 'a'.repeat(19);

    expect(component.formularioValido()).toBeFalse();

    // Descricao com exatamente 20 caracteres.
    component.descricao = 'a'.repeat(20);

    expect(component.formularioValido()).toBeTrue();

    // Espacos em branco nao devem contar como descricao.
    component.descricao = ' '.repeat(25);

    expect(component.formularioValido()).toBeFalse();

    // Verifica se um formulario invalido impede o envio.
    component.enviar();

    expect(apiMock.criar).not.toHaveBeenCalled();

  });

  // Teste 2: verifica a busca automatica do paciente.
  it('deve buscar o paciente apos 500 milissegundos', fakeAsync(() => {

    // Simula a resposta do AGHU.
    apiMock.buscarPaciente.and.returnValue(of(paciente));

    // Simula a digitacao de um prontuario muito curto.
    component.aoDigitar('12');

    tick(500);

    // Prontuarios menores que 3 caracteres nao sao consultados.
    expect(apiMock.buscarPaciente).not.toHaveBeenCalled();

    // Simula a digitacao de um prontuario valido.
    component.aoDigitar(' 12345 ');

    // Antes dos 500 milissegundos, nenhuma busca deve ocorrer.
    tick(499);

    expect(apiMock.buscarPaciente).not.toHaveBeenCalled();

    // Completa o tempo de espera.
    tick(1);

    // Verifica a remocao dos espacos e a consulta.
    expect(apiMock.buscarPaciente)
      .toHaveBeenCalledWith('12345');

    // Verifica os dados retornados.
    expect(component.paciente).toEqual(paciente);
    expect(component.buscando).toBeFalse();
    expect(component.erroPaciente).toBe('');

  }));

  // Teste 3: verifica o envio de uma solicitacao valida.
  it('deve enviar a solicitacao e limpar o formulario', fakeAsync(() => {

    // Preenche os campos obrigatorios.
    component.prontuario = '12345';
    component.paciente = paciente;
    component.especialidade = 'Cirurgia geral';
    component.descricao =
      ' Paciente necessita de acompanhamento especializado. ';

    // Simula uma resposta positiva do backend.
    apiMock.criar.and.returnValue(of({
      id: 10,
      status: 'Aguarda triagem'
    }));

    // Executa o envio da solicitacao.
    component.enviar();

    // Verifica os dados enviados ao servico.
    expect(apiMock.criar).toHaveBeenCalledWith({
      id_paciente: 1,
      especialidade: 'Cirurgia geral',
      descricao_clinica:
        'Paciente necessita de acompanhamento especializado.'
    });

    // Verifica o encerramento do carregamento.
    expect(component.enviando).toBeFalse();

    // Verifica a mensagem de sucesso.
    expect(component.sucesso).toContain('#10');
    expect(component.sucesso).toContain('Aguarda triagem');

    // Verifica se o formulario foi limpo.
    expect(component.prontuario).toBe('');
    expect(component.paciente).toBeNull();
    expect(component.especialidade).toBe('');
    expect(component.descricao).toBe('');

    // Verifica se o redirecionamento ainda nao ocorreu.
    expect(routerMock.navigate).not.toHaveBeenCalled();

    // Simula a espera de 1,5 segundo.
    tick(1500);

    // Verifica o redirecionamento para o acompanhamento.
    expect(routerMock.navigate)
      .toHaveBeenCalledWith(['/acompanhamento']);

  }));

});
