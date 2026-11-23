// Contenido del folleto. En producción vendrá del CMS (API REST/JSON).
// Cada diapositiva: sección, layout, fondo/media y bloques combinables.
window.BROCHURE = {
  marca: { nombre: "Universidad", sub: "CREATIVE CAMPUS" },
  secciones: [
    { id: "campus", titulo: "Creative Campus" },
    { id: "porque", titulo: "¿Por qué estudiar con nosotros?" },
    { id: "grados", titulo: "Grados" },
    { id: "admision", titulo: "Proceso de Admisión" },
    { id: "becas", titulo: "Becas y Ayudas" }
  ],
  slides: [
    {
      id: "portada", seccion: "campus", layout: "hero",
      fondo: "linear-gradient(135deg,#1d1d2b,#4a3b5c 60%,#c8102e)",
      etiqueta: "Grados", titulo: "Creative Campus",
      subtitulo: "Donde la creatividad no tiene límites"
    },
    {
      id: "por-que", seccion: "porque", layout: "split",
      media: { tipo: "carrusel", items: [
        { fondo: "linear-gradient(160deg,#f2a65a,#3aa0c8)", pie: "Talleres abiertos" },
        { fondo: "linear-gradient(160deg,#c8102e,#2b2b3a)", pie: "Laboratorio de animación" },
        { fondo: "linear-gradient(160deg,#ffd23f,#e4572e)", pie: "Plató y fotografía" }
      ]},
      titulo: "¿Por qué estudiar un grado en Creative Campus?",
      bloques: [
        { tipo: "iconos", items: [
          { icono: "📖", color: "#e4002b", titulo: "Modelo académico: Learning by Designing",
            texto: "Aprende con un modelo experiencial e integrador en el que las asignaturas se convierten en proyectos." },
          { icono: "🤝", color: "#3e5aa8", titulo: "Crea equipo",
            texto: "Potencia tus habilidades y relaciónate con personas con tus mismos intereses." }
        ]},
        { tipo: "enlace", texto: "Ver grados", destino: "area-moda" }
      ]
    },
    {
      id: "area-moda", seccion: "grados", layout: "fondo-tarjeta", titulo: "Área Diseño de Moda e Interiores",
      fondo: "linear-gradient(120deg,#c8102e 0 35%,#ffcf00 35% 100%)",
      bloques: [
        { tipo: "texto", html: "<p>Si sientes pasión por la moda, el diseño de interiores y la estética, esta área te ofrece la oportunidad de convertir esa pasión en una carrera gratificante.</p>" },
        { tipo: "popup", boton: "Escuela de Diseño asociada", titulo: "Escuela de Diseño",
          html: "<p>Estos grados oficiales se imparten en colaboración con una escuela con más de 60 años de experiencia, referente nacional e internacional.</p>" }
      ],
      enlaces: [
        { texto: "Grado en Diseño de Interiores", destino: "grado-interiores" },
        { texto: "Grado en Diseño y Creación de Moda", destino: "grado-moda" },
        { texto: "Grado en Comunicación y Gestión de Moda", destino: "grado-moda" }
      ]
    },
    {
      id: "grado-interiores", seccion: "grados", layout: "split", invertido: true,
      media: { tipo: "video-popup", fondo: "linear-gradient(160deg,#3b2416,#a86b3c)",
               url: "https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ" },
      titulo: "Grado en Diseño de Interiores",
      bloques: [
        { tipo: "texto", subtitulo: "Diseña los espacios del futuro",
          html: "<p><strong>Transformar los espacios para mejorar la calidad de vida de las personas que los habitan.</strong></p><p>Proyecta espacios contemporáneos: escenarios en realidad aumentada, instalaciones efímeras o el espacio doméstico y social.</p>" },
        { tipo: "desplegable", titulo: "Plan de estudios",
          html: "<ul><li>1º · Fundamentos del diseño, Dibujo, Historia del arte</li><li>2º · Proyectos I, Materiales, Iluminación</li><li>3º · Proyectos II, Diseño paramétrico</li><li>4º · Prácticas y Trabajo Fin de Grado</li></ul>" },
        { tipo: "descargas", items: [
          { texto: "Plan de estudios (PDF)", url: "#" },
          { texto: "Folleto del grado (PDF)", url: "#" }
        ]},
        { tipo: "datos", items: [
          ["📍", "Ubicación", "Creative campus"], ["⏱", "Duración", "4 años"],
          ["🎓", "Modalidad", "Presencial"], ["💬", "Idioma", "Español o Inglés"],
          ["📅", "Comienzo", "Septiembre"], ["🏅", "ECTS", "240"]
        ]}
      ]
    },
    {
      id: "grado-moda", seccion: "grados", layout: "split",
      media: { tipo: "video", fondo: "linear-gradient(160deg,#e4572e,#ffcf00)",
               url: "https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ" },
      titulo: "Grado en Diseño y Creación de Moda",
      bloques: [
        { tipo: "texto", subtitulo: "Vídeo + texto",
          html: "<p>Ejemplo de diapositiva con vídeo incrustado junto al texto. El vídeo se carga solo al llegar a la diapositiva.</p>" },
        { tipo: "enlace", texto: "Volver al área", destino: "area-moda" }
      ]
    },
    {
      id: "admision", seccion: "admision", layout: "hero",
      fondo: "linear-gradient(135deg,#2b2b3a,#3e5aa8)",
      etiqueta: "Admisión", titulo: "Proceso de Admisión",
      subtitulo: "Solicita información y te acompañamos en cada paso"
    },
    {
      id: "becas", seccion: "becas", layout: "hero",
      fondo: "linear-gradient(135deg,#c8102e,#1d1d2b)",
      etiqueta: "Becas", titulo: "Becas y Ayudas",
      subtitulo: "Más de 20 programas de ayudas al estudio"
    }
  ]
};
