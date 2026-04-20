# Registro de Capacidades: Antigravity HQ

Referencia rápida para el orquestador. Consultar antes de seleccionar el escuadrón.

## Fábrica Meta (Creación de Skills)

| Skill | Folder | Output | Interactiva |
|-------|--------|--------|:-----------:|
| `Creador de Talento` | `creador-de-talento` | SKILL.md + carpetas | Sí |

## Suite Ejecutiva y Comunicaciones

| Skill | Folder | Output | Interactiva |
|-------|--------|--------|:-----------:|
| `Secretario de Actas` | `secretario-de-actas` | Markdown | No |
| `Mentor de Redacción` | `mentor-de-redaccion` | .md / .Redactor Maestro | Sí |
| `Vocero de Equipo` | `vocero-de-equipo` | Markdown | No |

## Suite Ofimática y Analítica

| Skill | Folder | Output | Interactiva |
|-------|--------|--------|:-----------:|
| `Especialista en Datos` | `especialista-en-datos` | .xlsx / .csv | No |
| `Redactor Maestro` | `redactor-maestro` | .docx | No |
| `Presentador Ejecutivo` | `presentador-ejecutivo` | .pptx | No |
| `Gestor Documental` | `gestor-documental` | .pdf | No |

## Arte y Diseño

| Skill | Folder | Output | Interactiva |
|-------|--------|--------|:-----------:|
| `Guardián de Identidad` | `guardian-de-identidad` | SKILL.md (`brand-*`) | Sí |
| `Estratega Visual` | `estratega-visual` | Estilos aplicados | Sí |
| `Diseñador de Élite` | `disenador-de-elite` | .png + .md | No |

## Escuadrón de Ingeniería

| Skill | Folder | Output | Interactiva |
|-------|--------|--------|:-----------:|
| `Arquitecto de Conexiones` | `arquitecto-de-conexiones` | Servidor MCP | Sí |
| `Forjador de Apps` | `forjador-de-apps` | .html (bundle) | No |
| `Centinela de Calidad` | `centinela-de-calidad` | Informe QA | No |
| `Maestro de Arte IA` | `maestro-de-arte-ia` | .html + .md | No |
| `Maestro Desarrollador` | `maestro-desarrollador` | Código Fuente | No |

## Reglas de Precedencia
1. **`guardian-de-identidad`** siempre tiene prioridad sobre `estratega-visual`.
2. Skills interactivas **no pueden ejecutarse en paralelo**.
3. Verificar si existe un `guardian-de-identidad` ANTES de generar para asegurar coherencia de marca.

