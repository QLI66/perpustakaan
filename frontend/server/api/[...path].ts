import { getRequestURL, proxyRequest } from 'h3'

export default defineEventHandler((event) => {
    const requestUrl = getRequestURL(event)
    const apiPath = requestUrl.pathname.replace(/^\/api(?=\/|$)/, '')
    const target = new URL(`http://127.0.0.1:8000/api${apiPath}`)
    target.search = requestUrl.search

    return proxyRequest(event, target.toString())
})
