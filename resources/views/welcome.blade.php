<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Haus2House App</title>
  <style>
    /* Importamos las fuentes */
    @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@400;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans+Condensed:wght@300&display=swap');

    body {
      margin: 0;
      font-family: 'Nunito', sans-serif;
      background-color: #f6f6f6;
      color: #003845;
    }

    header {
      background-color: #faa307;
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    header h1 {
      margin: 0;
      font-size: 2.5rem;
      color: white;
      font-family: 'Open Sans Condensed', sans-serif;
      font-weight: normal;
      text-transform: uppercase;
      letter-spacing: 5px;
      background: linear-gradient(to right, #004350, #fa3c07);
      -webkit-background-clip: text;
      color: transparent;
      text-align: center;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.475);
    }

    h1, h2, h3 {
      font-family: 'Quicksand', sans-serif;
    }

    .container {
      display: flex;
    }

    aside {
      width: 150px;
      background-color: #005f73;
      padding: 2rem 1rem;
      min-height: calc(100vh - 70px);
    }

    aside nav a {
      display: block;
      color: #f0f0f0;
      text-decoration: none;
      margin-bottom: 1.5rem;
      font-weight: bold;
      font-family: 'Quicksand', sans-serif;
    }

    aside nav a:hover {
      text-decoration: underline;
    }

    main {
      flex: 1;
      padding: 2rem;
    }

    h2 {
      color: #005f73;
      font-family: 'Quicksand', sans-serif;
    }

    .card {
      background-color: white;
      border-radius: 8px;
      padding: 1.5rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
    }

    .button {
      display: inline-block;
      padding: 0.75rem 1.5rem;
      background-color: #faa307;
      color: white;
      border: none;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
      margin-top: 1rem;
      transition: background-color 0.3s;
      font-family: 'Fredoka', sans-serif;
    }

    .button:hover {
      background-color: #e08e00;
    }
  </style>
</head>
<body>

  <header>
    <h1>Haus2House</h1>
    <nav>
      <a href="#" style="color: white; text-decoration: none; font-weight: bold;">Cerrar sesión</a>
    </nav>
  </header>

  <div class="container">
    <aside>
      <nav>
        <a href="#">Inicio</a>
        <a href="#">Servicios</a>
        <a href="#">Trabajadores</a>
        <a href="#">Perfil</a>
      </nav>
    </aside>

    <main>
      <h2>Bienvenido, Gabs</h2>
      
      <div class="card">
        <h3>Buscar trabajadores disponibles</h3>
        <p>Encuentra trabajadores cerca de ti para servicios de limpieza, cocina, cuidado de mascotas y más.</p>
        <a href="#" class="button">Buscar ahora</a>
      </div>

      <div class="card">
        <h3>Mis servicios contratados</h3>
        <p>Consulta el estado de tus servicios actuales y haz seguimiento en tiempo real.</p>
        <a href="#" class="button">Ver servicios</a>
      </div>
    </main>
  </div>

</body>
</html>
