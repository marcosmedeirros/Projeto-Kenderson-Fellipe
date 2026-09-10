/* eslint-disable @typescript-eslint/no-require-imports */
// Ponto de entrada em produção (na Hostinger, "Entry file": server.js).
// Em desenvolvimento, use "npm run dev".

process.env.NODE_ENV ??= "production";

const { createServer } = require("node:http");
const nextModule = require("next");

const next = nextModule.default ?? nextModule;
const port = process.env.PORT || 3000;
const app = next({ dev: false });
const handle = app.getRequestHandler();

app
  .prepare()
  .then(() => {
    createServer((req, res) => handle(req, res)).listen(port, () => {
      console.log(`> Controladoria no ar (porta ${port})`);
    });
  })
  .catch((error) => {
    console.error("Não foi possível iniciar o painel:", error);
    process.exit(1);
  });
