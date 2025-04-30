<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notificación de Servicio Asignado</title>
</head>
<body style="margin: 0; font-family: 'Arial', sans-serif; background-color: #f6f6f6; color: #003845;">
  <!-- Usamos una tabla para mejor compatibilidad con clientes de correo -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f6f6f6;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; margin: 20px 0;">
          <!-- Encabezado -->
          <tr>
            <td style="background: #7f418b; background: linear-gradient(to right, #004350, #fa3c07); padding: 20px; text-align: center;">
              <h1 style="margin: 0; font-size: 36px; color: #faa307; font-family: 'Verdana', 'Arial Narrow', sans-serif; font-weight: normal; text-transform: uppercase; letter-spacing: 5px; text-shadow: 2px 2px 4px rgba(0,0,0,0.475);">
                HAUS2HOUSE
              </h1>
            </td>
          </tr>
          <!-- Contenido principal -->
          <tr>
            <td style="background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
              <h2 style="font-family: 'Arial', sans-serif; color: #005f73; border-bottom: 2px solid #faa307; padding-bottom: 5px; margin-bottom: 20px;">
                Hola, {{ $notifiable->nombre }}
              </h2>

              {!! $mensaje !!}

              <p style="margin: 10px 0;"><strong>Detalles:</strong> {{ $service->description }}</p>
              <p style="margin: 10px 0;"><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($service->start_time)->format('d/m/Y H:i') }}</p>

              <a href="{{ url('/services/' . $service->id) }}" style="display: inline-block; padding: 10px 20px; background-color: #faa307; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 15px; font-family: 'Arial', sans-serif;">
                Ver Servicio
              </a>

              <p style="margin-top: 20px;">¡Gracias por usar nuestra plataforma! 🌟</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>