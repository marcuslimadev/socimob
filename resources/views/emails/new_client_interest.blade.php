<!DOCTYPE html>
<html>
<head>
    <title>Novo Interesse em Imóvel</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Novo Interesse em Imóvel!</h1>
            </div>

            <!-- Content -->
            <div style="padding: 30px;">
                <p style="margin-top: 0;">Olá,</p>

                <p>Um cliente demonstrou interesse em um dos seus imóveis:</p>

                <!-- Client Info -->
                <div style="background-color: #ecfdf5; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #10b981;">
                    <h3 style="margin-top: 0; color: #065f46; font-size: 16px;">Dados do Cliente</h3>
                    <p style="margin: 5px 0;"><strong>Nome:</strong> {{ $clientName }}</p>
                    @if($clientEmail)
                    <p style="margin: 5px 0;"><strong>Email:</strong> {{ $clientEmail }}</p>
                    @endif
                    @if($clientPhone)
                    <p style="margin: 5px 0;"><strong>Telefone:</strong> {{ $clientPhone }}</p>
                    @endif
                </div>

                <!-- Property Info -->
                <div style="background-color: #f0f9ff; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #3b82f6;">
                    <h3 style="margin-top: 0; color: #1e40af; font-size: 16px;">Imóvel de Interesse</h3>
                    <p style="margin: 5px 0;"><strong>Título:</strong> {{ $propertyTitle }}</p>
                    @if($propertyCode)
                    <p style="margin: 5px 0;"><strong>Código:</strong> {{ $propertyCode }}</p>
                    @endif
                    @if($matchScore > 0)
                    <p style="margin: 5px 0;"><strong>Score de Conversão:</strong>
                        <span style="color: {{ $matchScore >= 70 ? '#059669' : ($matchScore >= 40 ? '#d97706' : '#dc2626') }}; font-weight: bold;">
                            {{ $matchScore }}%
                        </span>
                    </p>
                    @endif
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $leadUrl }}"
                       style="background-color: #3b82f6; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
                        Ver Lead no CRM
                    </a>
                </div>

                <p style="color: #6b7280; font-size: 14px;">
                    Recomendamos entrar em contato com o cliente o mais rápido possível para aumentar as chances de conversão.
                </p>
            </div>

            <!-- Footer -->
            <div style="background-color: #f7fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; color: #718096; font-size: 12px;">
                    Esta é uma notificação automática do {{ $tenantName }}.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
