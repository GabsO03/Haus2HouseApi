@extends('layout.app')

@section('content')
<form method="POST" action="{{route('login')}}">
    @csrf @method('POST')
    <div>            
        <label for="email">Email</label>
        <input type="email" id="email" name="email">
    </div>
    <div>
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password">
    </div>

    @error('email')
        <p>{{$message}}</p>
    @enderror

    <button type="submit">Ingresar</button>
</form>
@endsection