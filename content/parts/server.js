import { createServer } from 'node:http'

// #region start
const server = createServer((request, response) => {
  response.end('hello')
})
server.listen(8080)
// #endregion start

export default server
