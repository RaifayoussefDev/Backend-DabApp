@component('mail::message')
# Hello {{ $user->first_name }},

Your account has been created.

**Email:** {{ $user->email }}
**Password:** {{ $plainPassword }}

Please log in and change your password as soon as possible.

Thanks,
{{ config('app.name') }}
@endcomponent
