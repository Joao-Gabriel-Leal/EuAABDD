@extends('layouts.club', ['title' => 'Entrar | AABB Brasília'])

@section('content')
    <section class="login-screen">
        <div class="login-photo" style="background-image: url('https://aabbdf.com.br/wp-content/uploads/2022/09/complexosaquaticos.jpg')">
            <h1>Entrar na AABB Brasília</h1>
            <p>Use os acessos demo para navegar como associado ou equipe.</p>
        </div>

        <form class="login-form" method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <h2>Acesso ao clube digital</h2>
            <p>Equipe: equipe@aabb.demo · Associado: associado@aabb.demo · Senha: aabb2026</p>
            <label>E-mail <input type="email" name="email" value="{{ old('email', 'associado@aabb.demo') }}" required autofocus></label>
            <label>Senha <input type="password" name="password" value="aabb2026" required></label>
            <label class="check-row"><input type="checkbox" name="remember"> Manter conectado</label>
            @error('email')
                <span class="form-error">{{ $message }}</span>
            @enderror
            <button class="club-button club-button--yellow" type="submit">Entrar</button>
        </form>
    </section>
@endsection
