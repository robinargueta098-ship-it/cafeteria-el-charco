# Modelo de datos — Fase 2

Diagrama entidad-relación del esquema definido en
[`database/schema.sql`](../database/schema.sql), correspondiente a las
decisiones aprobadas en [arquitectura.md](arquitectura.md) (§5 y §10).

```mermaid
erDiagram
    ROLES ||--o{ USUARIOS : "tiene"
    USUARIOS ||--o{ BITACORA_AUDITORIA : "genera"
    USUARIOS ||--o{ LEALTAD_MOVIMIENTOS : "registra"

    CATEGORIAS ||--o{ PRODUCTOS : "agrupa"
    PRODUCTOS ||--o{ PRODUCTO_TEMPORADAS : "pertenece a"
    TEMPORADAS ||--o{ PRODUCTO_TEMPORADAS : "incluye"

    CATEGORIAS ||--o{ PROMOCIONES : "alcance opcional"
    PRODUCTOS ||--o{ PROMOCIONES : "alcance opcional"

    CLIENTES ||--o{ LEALTAD_MOVIMIENTOS : "acumula/canjea"
    PRODUCTOS ||--o{ LEALTAD_MOVIMIENTOS : "canjeado como"

    ROLES {
        int id PK
        varchar nombre
    }
    USUARIOS {
        int id PK
        int rol_id FK
        varchar usuario
        varchar password_hash
        tinyint activo
        datetime bloqueado_hasta
    }
    INTENTOS_LOGIN {
        bigint id PK
        varchar usuario_o_ip
        tinyint exitoso
        varchar ip
    }
    BITACORA_AUDITORIA {
        bigint id PK
        varchar tabla_afectada
        bigint registro_id
        enum accion
        int usuario_id FK
        json valores_antes
        json valores_despues
    }
    CATEGORIAS {
        int id PK
        varchar nombre
        varchar slug
        varchar notas
    }
    PRODUCTOS {
        int id PK
        int categoria_id FK
        varchar nombre
        decimal precio
        decimal precio_alt
        tinyint es_destacado
        tinyint es_mas_vendido
    }
    TEMPORADAS {
        int id PK
        varchar nombre
        date fecha_inicio
        date fecha_fin
    }
    PRODUCTO_TEMPORADAS {
        int producto_id FK
        int temporada_id FK
    }
    GALERIA {
        int id PK
        varchar imagen
        varchar titulo
    }
    PROMOCIONES {
        int id PK
        varchar nombre
        enum tipo
        int categoria_id FK
        int producto_id FK
        date fecha_inicio
        date fecha_fin
        tinyint combinable_con_lealtad
    }
    CLIENTES {
        int id PK
        varchar nombre
        varchar telefono
        smallint cafes_acumulados
    }
    LEALTAD_MOVIMIENTOS {
        bigint id PK
        int cliente_id FK
        enum tipo
        smallint cantidad
        int producto_canjeado_id FK
        int usuario_id FK
    }
    CONFIGURACION_SISTEMA {
        varchar clave PK
        text valor
    }
    HORARIOS_ATENCION {
        int id PK
        tinyint dia_semana
        time hora_apertura
        time hora_cierre
        tinyint cerrado
    }
```

`GALERIA` y `CONFIGURACION_SISTEMA` no tienen relaciones con otras tablas
(son independientes), tal como se definió en `arquitectura.md` §5.

## Notas de implementación

- `cafes_acumulados` en `clientes` es una **caché**: la fuente de verdad es
  la suma de `lealtad_movimientos`. Se mantiene sincronizada desde
  `LealtadService` (Fase 5), no se edita directamente desde SQL fuera de
  ese servicio.
- `intentos_login` no lleva llave foránea a propósito: debe poder registrar
  intentos con un usuario que no existe.
- La paleta de colores del negocio (beige/café/verde) **no** está en
  `configuracion_sistema`: es un token de diseño (CSS), no un dato que el
  panel deba editar en tiempo de ejecución.
- Los datos semilla de `schema.sql` usan exclusivamente información real de
  `docs/negocio.md` (menú completo con precios, WhatsApp, Instagram,
  Facebook). Dirección, mapa, calificación de Google y horarios quedan
  vacíos/sin configurar porque aún no se han proporcionado — no se
  inventaron valores de ejemplo.
