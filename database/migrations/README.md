# Migraciones

Esta carpeta se usa para **cambios incrementales** al esquema una vez que
`schema.sql` ya esté aplicado en producción (Hostinger) — por ejemplo,
agregar una columna o una tabla nueva más adelante.

Mientras el proyecto está en desarrollo inicial (Fases 2–9), los cambios de
esquema se hacen directamente en [`../schema.sql`](../schema.sql). Cuando el
sistema entre en producción, cada cambio posterior se agrega aquí como un
script numerado, por ejemplo:

```
001_agregar_columna_x.sql
002_agregar_tabla_y.sql
```

cada uno con instrucciones `ALTER TABLE` / `CREATE TABLE` idempotentes
cuando sea posible, y una línea equivalente en `schema.sql` para que el
esquema completo siga reflejando el estado actual.
