<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 20px; }
        .status-up { color: #10b981; font-weight: bold; }
        .status-down { color: #ef4444; font-weight: bold; }
        .details { background: #f9fafb; padding: 15px; border-radius: 6px; }
        .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Alerta de Monitor de Servicio</h2>
        </div>
        
        <p>Hola,</p>
        <p>El monitor <strong>{{ $monitor->name }}</strong> ha cambiado de estado.</p>
        
        <div class="details">
            <p><strong>Servicio:</strong> {{ $monitor->name }} ({{ $monitor->target }})</p>
            <p><strong>Estado Actual:</strong> 
                @if($status === 'up')
                    <span class="status-up">EN LÍNEA (UP) ✅</span>
                @else
                    <span class="status-down">CAÍDO (DOWN) 🔴</span>
                @endif
            </p>
            <p><strong>Hora del evento:</strong> {{ now()->format('d/m/Y H:i:s') }}</p>
            
            @if($status === 'down' && $errorMsg)
                <p><strong>Detalle del Error:</strong></p>
                <pre style="background: #fee2e2; padding: 10px; border-radius: 4px; overflow-x: auto;">{{ $errorMsg }}</pre>
            @endif
        </div>
        
        <p>Por favor, revisa tu panel de LaraPanel para más detalles.</p>
        
        <div class="footer">
            Generado automáticamente por LaraPanel - Uptime Monitor
        </div>
    </div>
</body>
</html>
