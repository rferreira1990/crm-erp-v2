<x-mail::message>
# Convite para aceder a plataforma

Foi convidado para aceder ao **{{ $appName }}** como utilizador da empresa **{{ $companyName }}**.

Se pretende ativar este acesso, confirme agora atraves do botao abaixo.

<x-mail::panel>
**Resumo do convite**

- **Empresa:** {{ $companyName }}
- **Perfil:** {{ $role }}
@if ($expiresAt)
- **Validade:** {{ $expiresAt }}
@endif
</x-mail::panel>

<x-mail::button :url="$invitationUrl" color="primary">
Aceitar convite
</x-mail::button>

Se o botao nao funcionar, copie e cole este link no navegador:

{{ $invitationUrl }}

Se nao reconhece este convite, pode ignorar este email com seguranca.

Cumprimentos,  
Equipa {{ $appName }}
</x-mail::message>
