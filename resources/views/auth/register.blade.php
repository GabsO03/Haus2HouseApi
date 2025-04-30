@extends('layout.app')

@section('content')
    <form method="POST" action="{{route('register')}}">
        @csrf @method('POST')
        <div>
            <label for="name">Nombre</label>
            <input type="text" id="name" name="name">
            @error('name')
                <p>{{$message}}</p>
            @enderror
        </div>
        <div>            
            <label for="apellidos">Apellidos</label>
            <input type="text" id="apellidos" name="apellidos">
            @error('apellidos')
                <p>{{$message}}</p>
            @enderror
        </div>
        <div>            
            <label for="email">Email</label>
            <input type="email" id="email" name="email">
            @error('email')
                <p>{{$message}}</p>
            @enderror
        </div>

        <div>
            <label for="profesor">Soy profesor</label>
            <input type="radio" name="rol" id="profesor" value="profesor">
            <label for="alumno">Soy alumno</label>
            <input type="radio" name="rol" id="alumno" value="alumno">
        </div>

        <div>
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password">
            @error('password')
                <p>{{$message}}</p>
            @enderror
        </div>

        <div>
            <label for="confirmation_password">Confirmar contraseña</label></div></div>
            <input type="password" id="confirmation_password" name="confirmation_password">
            @error('confirmation_password')
                <p>{{$message}}</p>
            @enderror
        </div>

        <button type="submit">Registrarme</button>
    </form>
@endsection