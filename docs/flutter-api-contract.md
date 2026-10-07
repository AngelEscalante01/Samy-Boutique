# Flutter API contract

Base URL local: `http://<host-accesible>:8001/api`. Todas las respuestas nuevas usan `{success, message, data}` y las listas paginadas agregan `meta` con los campos de Laravel. Los permisos se aplican en servidor mediante Spatie.

| Área | Método y ruta | Query/body que consume Flutter | Forma de `data` consumida | Permiso | Consumidor Flutter |
|---|---|---|---|---|---|
| Auth | POST `/login` | `{email,password}` | `{token,user:{id,name,email,phone,roles[],permissions[]}}` | Público + throttle | `AuthRepository`, `LoginResult`, auth `UserModel` |
| Auth | GET `/me` | ninguno | usuario autenticado, roles y permisos | Sanctum | `AuthRepository`, auth `UserModel` |
| Auth | POST `/logout` | ninguno | `null`; revoca token bearer actual | Sanctum | `AuthRepository` |
| POS | GET `/products` | `q,gender,category_id,page,per_page,updated_since,include_inactive,include_deleted` | paginado; producto con `stock`, `is_sellable`, `sync_action`, `sale_price`, `images[]`, `variants[]` (`id,sku,stock,active,sale_price_effective,size,color`) | `products.view` | `ProductRepository`, `ProductModel`, `ProductVariant` |
| Catálogos | GET `/catalogs/categories` | ninguno | lista `{id,name}` activa | `catalogs.manage` o `pos.view` | `CatalogRepository`, `CategoryModel` |
| Catálogos | GET `/catalogs/sizes` | ninguno | lista `{id,name}` | `catalogs.manage` o `pos.view` | `CatalogRepository`, `SizeModel` |
| Catálogos | GET `/catalogs/colors` | ninguno | lista `{id,name,hex}` | `catalogs.manage` o `pos.view` | `CatalogRepository`, `ColorModel` |
| POS | POST `/sales/preview` | acepta el payload de venta | `{totals:{...}}` de `PreviewSaleTotalsAction` | `sales.create` y reglas de descuento/cupón | `PreviewSaleRequest`; disponible para POS aunque no usado directamente por repositorio actual |
| Ventas | POST `/sales` | `customer_id?`, `items[{variant_id,qty,discount_type?,discount_value?}]`, descuentos opcionales, `coupon_code?`, `payments[{method,amount}]`, `dinero_recibido` | `{sale:{id,folio,status,subtotal,discount_total,total,cash_received,change,created_at,customer,cashier,items[],payments[]}}`; `cash_received` y `change` se persisten para detalle e impresión | `sales.create`; la acción valida stock, descuentos, cupón, pagos y transacción | `SaleRepository`, `StoreSalePayload`, `SaleModel` |
| Ventas | GET `/sales` | `q,status,payment_method,from,to,page,per_page` | lista paginada de resumen `{id,folio,status,created_at,total,cash_received,change,items_count,customer,cashier,payment}` | `sales.view` | `SalesRepository`, `SaleSummaryModel` |
| Ventas | GET `/sales/{id}` | ninguno | venta detallada con cliente, caja, partidas/variante y pagos | `sales.view` | `SalesRepository`, `SaleModel` |
| Ventas | GET `/sales/{id}/ticket` | ninguno | `{sale,business}` | `sales.view` | `SalesRepository`, ticket UI |
| Clientes | GET `/customers` | `q,page,per_page` | lista paginada `{id,name,email,phone,active,purchases_count,created_at,updated_at}` | `customers.view` | `CustomerRepository`, `CustomerModel` |
| Clientes | GET `/customers/{id}` | ninguno | `{customer,recent_sales[],recent_layaways[],stats:{total_spent,last_sale}}` | `customers.view` | `CustomerRepository`, `CustomerDetail` |
| Clientes | POST `/customers` | `{name,email?,phone?,active}` | `{customer:CustomerModel}` | `customers.create` | `CustomerRepository`, `CustomerPayload` |
| Clientes | PUT `/customers/{id}` | `{name,email?,phone?,active}` | `{customer:CustomerModel}` | `customers.update` | `CustomerRepository`, `CustomerPayload` |
| Apartados | GET `/layaways` | `status,q,page,per_page` | lista paginada con folio, total, anticipos, saldo, cliente y creador | `layaways.view` | `LayawayRepository`, `LayawayModel` |
| Apartados | GET `/layaways/{id}` | ninguno | resumen anterior más `items[]` con `product_id,variant_id,qty,unit_price,line_total,size,color` y `payments[]` | `layaways.view` | `LayawayRepository`, `LayawayDetail` |
| Apartados | POST `/layaways` | `customer_id?`, `items[{variant_id,qty}]`, anticipo opcional `anticipo_inicial,metodo_anticipo,referencia_anticipo` | `LayawayDetail` | `layaways.create`; delega en `LayawayService` | `LayawayRepository`, `StoreLayawayPayload` |
| Apartados | POST `/layaways/{id}/payments` | `{amount,method,reference?,observacion?,auto_liquidate?}` | `{payment,layaway,liquidated,sale_id}` | `layaways.pay`; delega en `LayawayService` | `LayawayRepository`, `AddPaymentResult` |
| Cortes | GET `/cash-cuts` | `page,per_page` | lista paginada `{id,cut_date,created_by,created_at,totals}` | rol `gerente` + `reports.view` | `CashCutRepository`, `CashCutRecord` |
| Cortes | POST `/cash-cuts/preview` | `{date:YYYY-MM-DD}` | `{totals:{date,sales_count,total_sold,profit_total,manual_discount_total,coupon_discount_total,loyalty_discount_total,cancelled_count,payments_by_method,top_products,top_categories}}` | rol `gerente` + `reports.view` | `CashCutRepository`, `CashCutTotals` |
| Cortes | POST `/cash-cuts` | `{date:YYYY-MM-DD}` | `{id,cut_date,created_by,created_at,totals}` | rol `gerente` + `reports.view` | `CashCutRepository`, `CashCutRecord` |
| Cupones | GET `/coupons` | `q,page,per_page,active?` | lista paginada de cupones | `coupons.manage` | `CouponRepository`, `CouponModel` |
| Reportes | GET `/reports` | `from,to` | `{filters,summary,top_categories[],top_products[],top_customers[],near_loyalty_customers[]}` | `reports.view` | `ReportRepository`, `ReportData` |
| Usuarios | GET `/users` | `q,page,per_page` | lista paginada `{id,name,email,active,roles[],created_at}` | rol `gerente` + `users.manage` | `UserRepository`, users `UserModel` |
| Configuración | GET `/settings` | ninguno | `{loyalty:{enabled,type,value}}` | rol `gerente` + `settings.manage` | No hay repository HTTP activo en Flutter actualmente |

## Hallazgos de alcance

- Se recorrieron `lib/features/**/data/*repository.dart`, configuraciones HTTP y proveedores POS. Los endpoints activos son los listados; impresoras y settings del teléfono se guardan localmente. No se halló llamada actual Flutter a `/sync/sales`, `/offline/snapshot` ni a las mutaciones web de catálogos/cupones/usuarios.
- El POS sincroniza productos incrementalmente por `updated_since` con `include_inactive` e `include_deleted`, luego sincroniza categorías. La API entrega variantes inactivas/cero stock marcadas como `sync_action=remove` en incremental para que se eliminen del Hive local.
- La venta usa `CreateSaleAction`; los apartados usan `LayawayService`; el corte usa `DailyCutService`, que comparte el agregado con el controlador web de reportes.
- `profit_total` aparece en el modelo Flutter, pero el cálculo web actual no produce utilidad por variante de forma confiable. Se deja en `0.0` en la respuesta en vez de inferir costos históricos; queda anotado como limitación funcional a resolver con snapshots de costo por partida.
