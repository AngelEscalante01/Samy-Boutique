<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
const props = defineProps({ recent: { type: Array, default: () => [] }, missingCategoryPrefixes: { type: Array, default: () => [] } });
const file = ref(null), mode = ref('create'), batch = ref(null), selected = ref([]), busy = ref(false), error = ref(''), helpOpen = ref(false);
let timer;
const eligible = computed(() => batch.value?.groups.filter(g => ['pending','failed'].includes(g.status)).map(g => g.id) ?? []);
const working = computed(() => batch.value?.groups.some(g => g.status === 'queued'));
const labels = {pending:'Listo para confirmar',invalid:'Corregir archivo',queued:'En cola',completed:'Completado',failed:'Falló; revisar y reintentar'};
function accept(data) { batch.value = data; mode.value = data.mode; selected.value = selected.value.filter(id => data.groups.some(g => g.id === id && ['pending','failed'].includes(g.status))); if (working.value) poll(); }
function poll() { clearTimeout(timer); timer = setTimeout(() => load(batch.value.id, true), 2000); }
async function load(id, silent = false) {
  if (!silent) error.value = '';
  try { const response = await axios.get(route('catalog-imports.show', id)); accept(response.data.data); }
  catch { error.value = 'No se pudo actualizar el lote. Usa Actualizar para continuar.'; }
}
async function preview() {
  if (!file.value) return;
  busy.value = true; error.value = '';
  try { const body = new FormData(); body.append('file',file.value); body.append('mode',mode.value); const res = await axios.post(route('catalog-imports.preview'),body); accept(res.data.data); selected.value = eligible.value; }
  catch (e) { error.value = Object.values(e.response?.data?.errors ?? {}).flat().join(' ') || 'No se pudo validar el archivo.'; }
  finally { busy.value = false; }
}
async function confirm() {
  busy.value = true; error.value = '';
  try { const res = await axios.post(route('catalog-imports.confirm', batch.value.id), {groups:selected.value}); accept(res.data.data); }
  catch(e) { error.value = Object.values(e.response?.data?.errors ?? {}).flat().join(' ') || 'No se pudo confirmar. Actualiza el lote antes de reintentar.'; }
  finally { busy.value = false; }
}
onUnmounted(() => clearTimeout(timer));
</script>
<template>
  <Head title="Importar productos" />
  <div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6 app-card-stack app-page">
    <div class="flex flex-wrap items-center justify-between gap-3 app-heading">
      <div><h1 class="text-2xl font-semibold text-stone-900">Importar y actualizar productos</h1><p class="text-sm text-stone-600">Excel: cargar, revisar y confirmar por producto.</p></div>
      <Link :href="route('products.index')" class="text-sm underline">Volver a productos</Link>
    </div>
    <section class="space-y-3 rounded-xl border bg-white p-4 app-surface app-card-stack">
      <label class="block text-sm font-medium">Modo <select v-model="mode" class="mt-1 block rounded-lg border-stone-300 text-sm"><option value="create">Crear productos nuevos</option><option value="update">Actualizar productos existentes</option></select></label>
      <div class="flex flex-wrap items-center gap-2">
        <a :href="mode === 'create' ? route('catalog-imports.template') : route('catalog-imports.export-update')" class="inline-block rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">{{ mode === 'create' ? 'Descargar plantilla Excel' : 'Descargar Excel para actualizar' }}</a>
        <button type="button" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50" :aria-expanded="helpOpen" aria-controls="excel-import-help" @click="helpOpen = !helpOpen">{{ helpOpen ? 'Ocultar ayuda' : '¿Cómo uso la plantilla?' }}</button>
      </div>
      <p v-if="mode === 'create' && missingCategoryPrefixes.length" class="text-sm text-amber-900">No aparecen {{ missingCategoryPrefixes.join(', ') }} en el desplegable: necesitan un prefijo SKU. <Link :href="route('catalogs.categories.index')" class="font-semibold underline underline-offset-2">Configurar categorías</Link> y descargar de nuevo.</p>
      <div v-show="helpOpen" id="excel-import-help" class="space-y-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-stone-800 app-surface">
        <h2 class="font-semibold text-base">{{ mode === 'create' ? 'Crear productos desde Excel' : 'Actualizar productos desde Excel' }}</h2>
        <ol class="list-decimal space-y-2 pl-5">
          <li>Descarga el archivo de este modo y ábrelo en Excel. Llena únicamente la hoja <strong>Productos</strong>; <strong>Instrucciones</strong> y <strong>Ejemplos</strong> muestran un modelo ya preparado.</li>
          <li v-if="mode === 'create'">Escribe la categoría, nombre y género en la primera fila del modelo. Para otra variante usa la fila siguiente: deja esos tres datos vacíos y selecciona otra talla o color. Un nombre escrito en una fila posterior inicia otro producto. No necesitas crear códigos ni grupos.</li>
          <li v-else>Conserva <code>producto_id</code>, <code>variante_id</code> y <code>version_producto</code> tal como se exportaron. Incluye todas las variantes de cada producto. Para agregar una variante, deja su <code>variante_id</code> vacío y captura talla, color y precio.</li>
          <li>Las celdas azul claro tienen desplegables con opciones activas del catálogo actual. Cada producto puede tener hasta tres ocasiones o estilos en el Excel; en su ficha puedes asociar más. Para crear productos, una categoría también necesita prefijo SKU. Si cambias catálogos, descarga de nuevo la plantilla.</li>
          <li>Guarda como <strong>.xlsx</strong>, selecciónalo aquí y pulsa <strong>Revisar archivo</strong>. La revisión muestra errores y productos; todavía no crea ni cambia nada.</li>
          <li>Selecciona los grupos válidos y pulsa <strong>Confirmar</strong>. Puedes consultar el avance en “Mis lotes recientes” y después abrir la edición masiva para agregar fotos.</li>
        </ol>
        <div v-if="mode === 'create'" class="space-y-2">
          <p class="font-semibold">Columnas de la hoja Productos</p>
          <div class="overflow-x-auto"><table class="min-w-full border-collapse text-left"><thead><tr class="border-b border-amber-200"><th class="py-1 pr-4">Columna</th><th class="py-1">Qué escribir</th></tr></thead><tbody>
            <tr class="border-b border-amber-100"><td class="py-1 pr-4 font-mono">categoria, nombre, genero</td><td>En la primera fila de cada modelo. Categoría y género se eligen del desplegable; el nombre lo escribes tú. En sus variantes siguientes pueden quedar vacíos.</td></tr>
            <tr class="border-b border-amber-100"><td class="py-1 pr-4 font-mono">talla, color</td><td>Elígelos del desplegable en cada variante. No repitas la misma combinación dentro de un modelo.</td></tr>
            <tr class="border-b border-amber-100"><td class="py-1 pr-4 font-mono">precio_venta</td><td>Precio de esa variante con punto decimal y máximo dos decimales, por ejemplo 599.00.</td></tr>
            <tr class="border-b border-amber-100"><td class="py-1 pr-4 font-mono">existencia_inicial</td><td>Unidades de esa variante: número entero de 0 a 999999.</td></tr>
            <tr><td class="py-1 pr-4 font-mono">descripcion, ocasion_1, ocasion_2, ocasion_3, costo</td><td>Opcionales. Elige hasta tres ocasiones o estilos distintos en sus desplegables. Las mismas selecciones aplican a todas las variantes del producto. Puedes añadir más desde su ficha. Costo aparece solo si tu usuario tiene permiso.</td></tr>
          </tbody></table></div>
          <p>Samy genera los SKU y códigos de barras al confirmar; no agregues columnas para inventarlos.</p>
        </div>
        <p v-else>Las celdas vacías conservan el valor actual. El Excel muestra hasta tres ocasiones por producto; las adicionales se conservan y se editan en la ficha del producto. Este modo no modifica existencias: usa el flujo de inventario para entradas o ajustes. Una variante nueva empieza con stock cero. Si el producto cambió después de exportarlo, descarga el Excel otra vez.</p>
        <p class="text-stone-700">Límite: <strong>1000 filas</strong> y <strong>5 MB</strong> por archivo. Usa valores, sin fórmulas ni macros. Si un grupo falla, corrige el archivo y vuelve a cargarlo; repetir el mismo archivo no duplica productos.</p>
      </div>
      <p v-if="mode === 'create'" class="text-sm text-stone-600">Una fila por talla y color; un nombre nuevo inicia otro producto. El Excel trae listas del catálogo y un ejemplo de variantes. Máximo 1000 filas y 5 MB. Samy genera los SKU.</p>
      <p v-else class="text-sm text-stone-600">Edita la exportación sin cambiar producto_id, variante_id ni version_producto. Las celdas vacías conservan el dato actual. Una variante nueva deja variante_id vacío, requiere talla, color y precio, e inicia con stock cero; usa inventario para ingresar existencias. No se cambia stock con este modo.</p>
      <label class="block text-sm font-medium">Archivo Excel <input type="file" accept=".xlsx" class="mt-1 block w-full text-sm" @change="file = $event.target.files[0]" /></label>
      <button :disabled="!file || busy" class="rounded-lg border px-4 py-2 disabled:opacity-40" @click="preview">{{ busy ? 'Procesando…' : 'Revisar archivo' }}</button>
      <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
    </section>
    <section v-if="!batch && props.recent.length" class="rounded-xl border bg-white p-4 app-surface">
      <h2 class="mb-2 font-semibold">Mis lotes recientes</h2>
      <button v-for="item in props.recent" :key="item.id" class="block py-1 text-left text-sm underline" @click="load(item.id)">{{ item.filename }} · {{ item.created_at }}</button>
    </section>
    <section v-if="batch" class="space-y-4 rounded-xl border bg-white p-4 app-surface app-card-stack">
      <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-semibold">{{ batch.filename }}</h2><button class="text-sm underline" @click="load(batch.id)">Actualizar</button></div>
      <p class="text-sm">{{ batch.groups.length }} grupos: <span v-for="(count,status) in batch.counts" :key="status" class="mr-3">{{ labels[status] }}: {{ count }}</span></p>
      <p class="text-sm text-stone-600">Solo se procesan los grupos seleccionados. Cada producto se guarda completo o no se guarda. Si cambió desde la exportación, descarga una nueva. Un archivo ya procesado abre el lote anterior sin duplicarlo.</p>
      <div class="flex flex-wrap gap-3"><button :disabled="busy || !eligible.length" class="text-sm underline" @click="selected = eligible">Seleccionar disponibles</button><button :disabled="busy || !selected.length" class="rounded-lg bg-stone-900 px-4 py-2 text-sm text-white disabled:opacity-40" @click="confirm">Confirmar {{ selected.length }} grupos</button><Link v-if="batch.counts.completed" :href="route('products.bulk', {import_batch:batch.id})" class="rounded-lg border px-4 py-2 text-sm">Agregar fotos al lote en tabla</Link></div>
      <p v-if="working" class="text-sm text-amber-800">Procesando en segundo plano. Puedes salir y volver desde Mis lotes recientes.</p>
      <article v-for="group in batch.groups" :key="group.id" class="space-y-2 rounded-lg border p-3 app-surface">
        <div class="flex flex-wrap items-center gap-3"><input v-if="['pending','failed'].includes(group.status)" v-model="selected" :value="group.id" type="checkbox" :aria-label="`Seleccionar ${group.key}`" /><strong>{{ group.key }}</strong><span class="text-sm">{{ labels[group.status] }}</span><Link v-if="group.product_id" :href="route('products.edit', group.product_id)" class="ml-auto text-sm underline">Editar y agregar fotos</Link></div>
        <ul v-if="group.errors.length" class="list-inside list-disc text-sm text-red-700"><li v-for="message in group.errors" :key="message">{{ message }}</li></ul>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr><th class="p-1">Fila</th><th v-if="batch.mode === 'update'" class="p-1">Variante</th><th class="p-1">Prenda</th><th class="p-1">Categoría</th><th class="p-1">Talla/color</th><th class="p-1">Ocasiones o estilos</th><th class="p-1">Precio</th><th v-if="group.rows[0]?.costo !== undefined" class="p-1">Costo</th><th v-if="batch.mode === 'create'" class="p-1">Unidades</th></tr></thead><tbody><tr v-for="row in group.rows" :key="row._row"><td class="p-1">{{ row._row }}</td><td v-if="batch.mode === 'update'" class="p-1">{{ row.variante_id || 'Nueva' }}</td><td class="p-1">{{ row.nombre }}</td><td class="p-1">{{ row.categoria }}</td><td class="p-1">{{ row.talla }} / {{ row.color }}</td><td class="p-1">{{ row.ocasiones || '—' }}</td><td class="p-1">{{ row.precio_venta }}</td><td v-if="row.costo !== undefined" class="p-1">{{ row.costo || '—' }}</td><td v-if="batch.mode === 'create'" class="p-1">{{ row.existencia_inicial }}</td></tr></tbody></table></div>
      </article>
    </section>
  </div>
</template>
