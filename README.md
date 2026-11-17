# Portal de reportes Power BI

Portal privado para publicar dashboards de **Power BI** a clientes, con inicio de sesión, gestión de usuarios y un flujo de integración de encuestas desde **QuestionPro**. Se utilizó para entregar avances de estudios *Mystery Shopper*.

## Funcionalidades

- **Autenticación** con contraseñas cifradas (`password_hash`) y bitácora de inicios de sesión.
- **Gestión de usuarios** (solo administradores): crear, editar, activar/desactivar, restablecer contraseña y subir avatar.
- **Visualización del dashboard** de Power BI embebido, protegido por sesión y con *Content-Security-Policy* restringida a los dominios de Power BI.
- **Webhook de encuestas** (`Powerbi_encuesta.php`): recibe respuestas en JSON desde QuestionPro y las guarda en MySQL.
- **Importador de Excel** (`import_excel.php`): convierte exportaciones de QuestionPro a JSON, con mapeo de códigos de respuesta a etiquetas legibles.
- Endurecimiento de seguridad en `.htaccess` (cabeceras, bloqueo de archivos sensibles y de agentes vacíos).

## Tecnologías

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8, PDO |
| Base de datos | MySQL |
| Librerías | PhpSpreadsheet (Composer) |
| Frontend | HTML, CSS, JavaScript vanilla |
| BI | Power BI (Publish to web) |

## Estructura

```text
config.example.php       Configuración de ejemplo (copiar como config.php)
index.php                Dashboard embebido
login.php / logout.php   Autenticación
users.php                Administración de usuarios
Powerbi_encuesta.php     Webhook de respuestas de encuestas
import_excel.php         Conversión de Excel de QuestionPro a JSON
app/                     Auth, conexión a BD y helpers
includes/                Encabezado y pie de página
database/schema_auth.sql Esquema de usuarios y auditoría
assets/                  CSS, JS e imágenes
```

## Instalación

```bash
composer install
cp config.example.php config.php   # completa credenciales y URL del reporte
mysql -u root -p powerbi < database/schema_auth.sql
```

---

## Autor

**Ariel Geovanny Andino Chinchilla** — Desarrollador Full Stack

- GitHub: [@andino1234](https://github.com/andino1234)
- Repositorio: [github.com/andino1234/power](https://github.com/andino1234/power)

Proyecto diseñado y desarrollado por Ariel Geovanny Andino Chinchilla para Insight LATAM. Se publica con fines de portafolio; las credenciales, datos de clientes y archivos de usuarios se excluyeron del repositorio.
