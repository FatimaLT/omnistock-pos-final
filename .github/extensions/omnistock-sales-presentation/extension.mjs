import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createCanvas, CanvasError, joinSession } from '@github/copilot-sdk/extension';
import declaration from './extension.json' with { type: 'json' };

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pagePath = path.join(__dirname, 'presentation.html');
const instances = new Map();

function safeSlide(value) {
  const slide = Number(value);
  return Number.isInteger(slide) && slide >= 1 && slide <= 13 ? slide : null;
}

function createServer(instanceId) {
  const state = instances.get(instanceId) || { slide: 1 };
  instances.set(instanceId, state);

  const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://127.0.0.1');

    if (url.pathname.startsWith('/assets/')) {
      const assets = {
        'omnistock-login.png': path.join(__dirname, 'assets', 'omnistock-login.png'),
        'omnistock-pos.png': path.join(__dirname, 'assets', 'omnistock-pos.png'),
        'omnistock-dashboard.png': path.join(__dirname, 'assets', 'omnistock-dashboard.png'),
        'omnistock-messages.png': path.join(__dirname, 'assets', 'omnistock-messages.png'),
      };
      const file = assets[path.basename(url.pathname)];
      if (!file) {
        response.writeHead(404);
        response.end('No encontrado');
        return;
      }
      fs.readFile(file, (error, image) => {
        if (error) {
          response.writeHead(404);
          response.end('No encontrado');
          return;
        }
        response.writeHead(200, { 'Content-Type': 'image/png', 'Cache-Control': 'no-store' });
        response.end(image);
      });
      return;
    }

    if (url.pathname === '/api/state' && request.method === 'GET') {
      response.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
      response.end(JSON.stringify(state));
      return;
    }

    if (url.pathname === '/api/state' && request.method === 'POST') {
      let body = '';
      request.on('data', chunk => {
        body += chunk;
        if (body.length > 1024) request.destroy();
      });
      request.on('end', () => {
        try {
          const input = JSON.parse(body);
          const slide = safeSlide(input.slide);
          if (!slide) throw new Error('Diapositiva no válida');
          state.slide = slide;
          response.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          response.end(JSON.stringify(state));
        } catch {
          response.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          response.end(JSON.stringify({ error: 'Entrada no válida' }));
        }
      });
      return;
    }

    if (url.pathname !== '/') {
      response.writeHead(404);
      response.end('No encontrado');
      return;
    }

    try {
      const html = fs.readFileSync(pagePath);
      response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' });
      response.end(html);
    } catch (error) {
      throw error;
    }
  });

  server.listen(0, '127.0.0.1');
  return server;
}

const actionHandlers = {
  next_slide: async ({ instanceId }) => {
    const state = instances.get(instanceId);
    if (!state) throw new CanvasError('not_open', 'La presentación no está abierta.');
    state.slide = Math.min(13, state.slide + 1);
    return { slide: state.slide };
  },
  previous_slide: async ({ instanceId }) => {
    const state = instances.get(instanceId);
    if (!state) throw new CanvasError('not_open', 'La presentación no está abierta.');
    state.slide = Math.max(1, state.slide - 1);
    return { slide: state.slide };
  },
  go_to_slide: async ({ instanceId, input }) => {
    const state = instances.get(instanceId);
    if (!state) throw new CanvasError('not_open', 'La presentación no está abierta.');
    const slide = safeSlide(input?.slide);
    if (!slide) throw new CanvasError('invalid_slide', 'La diapositiva debe estar entre 1 y 13.');
    state.slide = slide;
    return { slide: state.slide };
  },
};

const canvas = declaration.canvases[0];
await joinSession({
  canvases: [
    createCanvas({
      ...canvas,
      actions: canvas.actions.map(action => ({ ...action, handler: actionHandlers[action.name] })),
      open: async ({ instanceId }) => {
        const existing = instances.get(instanceId);
        if (existing?.server?.listening && existing.url) {
          return { url: existing.url };
        }
        if (!existing) instances.set(instanceId, { slide: 1 });
        const server = createServer(instanceId);
        await new Promise((resolve, reject) => {
          if (server.listening) resolve();
          else {
            server.once('listening', resolve);
            server.once('error', reject);
          }
        });
        const address = server.address();
        if (!address || typeof address === 'string') throw new CanvasError('server_error', 'No se pudo iniciar la presentación.');
        const url = `http://127.0.0.1:${address.port}/`;
        const state = instances.get(instanceId);
        state.server = server;
        state.url = url;
        return { url };
      },
      onClose: async ({ instanceId }) => {
        const state = instances.get(instanceId);
        if (state?.server) {
          await new Promise(resolve => state.server.close(resolve));
        }
        instances.delete(instanceId);
      },
    }),
  ],
});
