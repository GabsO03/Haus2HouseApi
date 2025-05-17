<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Código de Verificación</title>
</head>
<body style="margin: 0; font-family: 'Arial', sans-serif; background-color: #f6f6f6; color: #003845;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f6f6f6;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; margin: 20px 0;">
          <tr>
            <td style="background: #7f418b; background: linear-gradient(to right, #004350, #fa3c07); padding: 20px; text-align: center;">
              <h1 style="margin: 0; font-size: 36px; color: #faa307; font-family: 'Verdana', 'Arial Narrow', sans-serif; font-weight: normal; text-transform: uppercase; letter-spacing: 5px; text-shadow: 2px 2px 4px rgba(0,0,0,0.475);">
                HAUS2HOUSE
              </h1>
            </td>
          </tr>
          <tr>
            <td style="background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
              <h2 style="font-family: 'Arial', sans-serif; color: #005f73; border-bottom: 2px solid #faa307; padding-bottom: 5px; margin-bottom: 20px;">
                Hola, {{ $notifiable->nombre }}
              </h2>

              <p>Hemos recibido una solicitud para cambiar tu contraseña. Usa el siguiente código de verificación:</p>
              
              <h3 style="font-size: 24px; color: #faa307; text-align: center; margin: 20px 0;">{{ $code }}</h3>

              <p>Este código es válido por 1 hora. Si no solicitaste este cambio, ignora este correo.</p>

              <p style="margin-top: 20px;">¡Gracias por usar nuestra plataforma! 🌟</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>