@extends('layouts.app')
@section('title', 'Не найдено')

@section('content')
<div class="error-page">
    <h1 class="display">Такого билета нет</h1>
    <p>Мероприятие удалили или в адресе опечатка. Вся актуальная афиша на главной.</p>
    <a class="btn" href="{{ route('events.index') }}">Открыть афишу</a>
</div>
@endsection
