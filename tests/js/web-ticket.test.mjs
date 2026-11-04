import test from 'node:test'
import assert from 'node:assert/strict'
import { printSale, printLayawayCreated, printLayawayPayment, printLayawayClosed, validatePrinterReadyForCheckout } from '../../resources/js/services/printSale.js'

globalThis.route = (name, params) => `/${name}/${typeof params === 'object' ? JSON.stringify(params) : params}`

test('browser checkout offers a receipt without opening popups or reporting an Android failure', async () => {
    globalThis.window = { open() { throw new Error('Automatic popup is not allowed') } }
    globalThis.fetch = () => { throw new Error('Automatic preview must not record a failed native print') }
    assert.equal(validatePrinterReadyForCheckout().ready, true)
    for (const result of [await printSale(1, { automatic: true }), await printLayawayCreated(2, { automatic: true }), await printLayawayPayment(2, 3, { automatic: true }), await printLayawayClosed(2, { automatic: true })]) {
        assert.equal(result.ok, true)
        assert.equal(result.mode, 'browser')
        assert.ok(result.ticketUrl)
    }
})

test('explicit browser printing opens the authorized receipt and handles blocked popups', async () => {
    const opened = []
    const preview = { opener: 'parent' }
    globalThis.window = { open(url, target) { opened.push([url, target]); return preview } }
    const result = await printSale(9)
    assert.equal(result.ok, true)
    assert.deepEqual(opened, [['/sales.ticket/9', '_blank']])
    assert.equal(preview.opener, null)
    globalThis.window = { open: () => null }
    assert.equal((await printLayawayCreated(3)).ok, false)
})

test('native printing still obtains the ticket, records an attempt and sends to the Android bridge', async () => {
    let printed
    const requests = []
    globalThis.document = { querySelector: () => ({ getAttribute: () => 'csrf-test' }) }
    globalThis.window = { AndroidPrinter: { getStatus: () => 'READY', printTicket: value => { printed = JSON.parse(value) } } }
    const ticket = { folio: '17', total: 300, items: [], meta: { sale_id: 17, ticket_type: 'sale' } }
    globalThis.fetch = async (url, options) => {
        requests.push([url, options.method])
        return { ok: true, json: async () => ticket }
    }
    assert.equal((await printSale(17)).ok, true)
    assert.deepEqual(printed, ticket)
    assert.deepEqual(requests.map(value => value[1]), ['GET', 'POST'])
})
