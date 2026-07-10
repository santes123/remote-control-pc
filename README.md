# Remote Control PC (WOL + Guacamole + Laravel)

## Planteamiento cerrado (decisiones)

- Acceso desde fuera: solo por VPN (Tailscale).
- VPN: Tailscale.
- Backend (Laravel) en servidor final: nativo en Ubuntu (nginx + php-fpm).
- Escritorio remoto: solo RDP vía Apache Guacamole (usuarios persistentes en MySQL).
- Windows destino y Ubuntu server en la misma LAN para Wake-on-LAN.
- La VPN solo “protege” la web/Guacamole: se publican únicamente en la interfaz Tailscale. SSH del servidor sigue igual.

## Dónde usamos Docker y por qué

- Guacamole + guacd + MySQL: Docker Compose
  - Instalación nativa de Guacamole es más frágil (dependencias, Tomcat, guacd, esquema de BD).
  - Docker permite levantar lo mismo en Windows (dev) y Ubuntu (prod) con el mismo `docker-compose.yml`.
  - La persistencia real está en MySQL (usuarios, conexiones, historial si se usa).
- Laravel (backend): nativo en Ubuntu (prod)
  - Encaja con tu decisión 3B.
  - Facilita integración con herramientas del host (WOL broadcast en LAN, SSH hacia Windows destino, firewall).
  - En Windows (dev) se recomienda ejecutar Laravel en WSL2 Ubuntu para parecerse al servidor final.

## Estructura del repo

- `backend/`: API Laravel para Wake-on-LAN, estado del PC y acciones remotas
- `infra/guacamole/`: Guacamole + MySQL (Docker Compose)

## Instalación en Windows (entorno dev “como si fuera Ubuntu”)

### Requisitos

- Windows 10/11 con virtualización activada
- WSL2 + Ubuntu (recomendado)
- Docker Desktop (backend WSL2)

### 1) Preparar Docker Desktop + WSL2

1. Instala WSL2 y Ubuntu:

```powershell
wsl --install -d Ubuntu
```

2. Reinicia Windows cuando lo pida.
3. Completa el primer arranque de Ubuntu (usuario y contraseña).
4. Instala Docker Desktop y activa:
   - Use the WSL 2 based engine
   - Integración con tu distro Ubuntu de WSL

### 2) Preparar Laravel dentro de WSL2

1. Entra en Ubuntu (WSL).
2. Instala dependencias base:

```bash
sudo apt update
sudo apt install -y php-cli php-mbstring php-xml php-curl php-sqlite3 php-zip unzip curl git openssh-client composer
```

3. Ve al backend del proyecto:

```bash
cd /mnt/d/proyects/remote-control-pc/backend
```

4. Copia variables y ajusta el destino:

```bash
cp .env.example .env
```

5. Edita `.env` y configura como mínimo:
   - `REMOTE_PC_HOST=192.168.1.50`
   - `REMOTE_PC_SSH_HOST=192.168.1.50`
   - `REMOTE_PC_SSH_USER=tu_usuario_windows`
   - `REMOTE_PC_SSH_PRIVATE_KEY_PATH=/home/tu_usuario/.ssh/rcp_windows`
   - `REMOTE_PC_MAC=AA:BB:CC:DD:EE:FF`
   - `REMOTE_PC_GUACAMOLE_BASE_URL=http://localhost:8080/guacamole/`
   - si quieres abrir una conexión concreta al pulsar el botón:
     - `REMOTE_PC_GUACAMOLE_CONNECTION_ID=1`
     - `REMOTE_PC_GUACAMOLE_AUTH_PROVIDER=mysql`
     - o directamente `REMOTE_PC_GUACAMOLE_CLIENT_IDENTIFIER=<copiado_de_la_url_de_guacamole>`

6. Genera clave SSH si aún no existe:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/rcp_windows -C "remote-control"
```

7. Copia la clave pública al Windows destino, en el `authorized_keys` del usuario remoto.
8. Arranca Laravel:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

9. Prueba el primer endpoint:

```bash
curl http://127.0.0.1:8000/api/pc/status
```

### 3) Qué devuelve el endpoint inicial

- Ruta: `GET /api/pc/status`
- Comprueba si el puerto RDP responde.
- Comprueba si el puerto SSH responde.
- Si hay usuario y clave configurados, ejecuta una prueba real por SSH con `echo ssh-ok`.
- Devuelve `flow.next_action` para modelar el flujo de encendido y acceso.
- Devuelve `flow.control_url` cuando el estado ya es `available`.
- Estados actuales:
  - `offline`: no responde ni RDP ni SSH
  - `booting`: responde algún puerto, pero la prueba SSH aún no entra
  - `available`: la prueba SSH ya funciona correctamente

### 3.1) Encender el PC (Wake-on-LAN)

- Ruta: `POST /api/pc/wake`
- Env requerido:
  - `REMOTE_PC_MAC`
  - `REMOTE_PC_WOL_BROADCAST` (por defecto `255.255.255.255`, pero en Windows suele funcionar mejor el broadcast de la subred, por ejemplo `192.168.1.255`)
  - `REMOTE_PC_WOL_PORT` (por defecto `9`)

Ejemplo:

```bash
curl -X POST http://127.0.0.1:8000/api/pc/wake
```

### 3.2) Apagar y reiniciar (SSH)

- Ruta: `POST /api/pc/shutdown`
- Ruta: `POST /api/pc/restart`
- Env requeridos:
  - `REMOTE_PC_SSH_HOST`
  - `REMOTE_PC_SSH_USER`
  - `REMOTE_PC_SSH_PRIVATE_KEY_PATH` (la clave privada, no el `.pub`)
- Comandos configurables:
  - `REMOTE_PC_SHUTDOWN_COMMAND` (por defecto Windows: `shutdown /s /t 0`)
  - `REMOTE_PC_RESTART_COMMAND` (por defecto Windows: `shutdown /r /t 0`)

Ejemplos:

```bash
curl -X POST http://127.0.0.1:8000/api/pc/shutdown
curl -X POST http://127.0.0.1:8000/api/pc/restart
```

### 3.3) Obtener el enlace de control remoto

- Ruta: `GET /api/pc/control-link`
- Si el PC sigue apagado o arrancando, devuelve `409 Conflict`.
- Si ya está `available`, devuelve `control_url`.
  - Si no has configurado una conexión concreta, será la home de Guacamole.
  - Si has configurado `REMOTE_PC_GUACAMOLE_CLIENT_IDENTIFIER` o `REMOTE_PC_GUACAMOLE_CONNECTION_ID`, será una URL directa tipo `#/client/...`.

Ejemplo:

```bash
curl http://127.0.0.1:8000/api/pc/control-link
```

### 3.4) Flujo completo recomendado

1. Consultar estado:

```bash
curl http://127.0.0.1:8000/api/pc/status
```

2. Si `status=offline` y `flow.next_action=wake`, encender:

```bash
curl -X POST http://127.0.0.1:8000/api/pc/wake
```

3. Hacer polling cada 5 segundos:

```bash
curl http://127.0.0.1:8000/api/pc/status
```

4. Cuando `status=available`, abrir Guacamole:

```bash
curl http://127.0.0.1:8000/api/pc/control-link
```

### 3.5) Interfaz gráfica web

- Ruta web principal: `GET /`
- Ruta alternativa: `GET /dashboard`
- La pantalla incluye:
  - botón `Encender PC`
  - polling automático del estado cada 5 segundos
  - polling temporalmente más rápido tras acciones sensibles
  - botón `Abrir Guacamole` cuando el estado es `available`
  - botones `Reiniciar` y `Apagar`
  - confirmación visual antes de reiniciar o apagar
  - overlay de carga con spinner mientras una acción está en curso
  - popups con SweetAlert2
  - historial local de acciones en el navegador
  - bloque de diagnóstico con la última respuesta JSON
  - comprobación separada de accesibilidad del gateway Guacamole

Si has compilado los assets con Vite:

```bash
npm install
npm run build
```

luego solo necesitas arrancar Laravel:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

y abrir:

```text
http://127.0.0.1:8000/
```

## Cómo probarlo ahora mismo en Windows

1. Asegúrate de que Guacamole esté levantado:

```powershell
cd d:\proyects\remote-control-pc\infra\guacamole
docker compose up -d
```

2. En `backend\.env`, revisa:
   - `REMOTE_PC_HOST` y `REMOTE_PC_SSH_HOST` deben apuntar al mismo PC destino
   - `REMOTE_PC_SSH_PRIVATE_KEY_PATH` debe apuntar a la clave privada, no a `.pub`
   - si en Windows `ssh` no aparece en el PATH de PHP, usa `REMOTE_PC_SSH_BINARY=C:/Windows/System32/OpenSSH/ssh.exe`
   - `REMOTE_PC_MAC` debe contener la MAC real del PC destino
   - `REMOTE_PC_GUACAMOLE_BASE_URL` debe apuntar a tu Guacamole local o remoto
   - si quieres abrir una conexión concreta, añade `REMOTE_PC_GUACAMOLE_CONNECTION_ID` y `REMOTE_PC_GUACAMOLE_AUTH_PROVIDER=mysql`
   - también puedes pegar `REMOTE_PC_GUACAMOLE_CLIENT_IDENTIFIER` con el valor exacto que aparece tras `#/client/` en una URL real de Guacamole

3. Limpia caché de config y arranca Laravel:

```powershell
cd d:\proyects\remote-control-pc\backend
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

4. Prueba SSH manual:

```powershell
ssh -i C:/Users/anton/.ssh/conexion-server-pc anto@192.168.1.16 "echo ssh-ok"
```

5. Prueba la API:

```powershell
curl.exe -H "Accept: application/json" http://127.0.0.1:8000/api/pc/status
curl.exe -H "Accept: application/json" -X POST http://127.0.0.1:8000/api/pc/wake
curl.exe -H "Accept: application/json" -X POST http://127.0.0.1:8000/api/pc/restart
curl.exe -H "Accept: application/json" http://127.0.0.1:8000/api/pc/control-link
```

6. Abre la interfaz web:

```powershell
start http://127.0.0.1:8000/
```

### 3.6) Diagnóstico rápido de Guacamole

- Si `http://localhost:8080/guacamole/` no abre y `curl.exe -I http://localhost:8080/guacamole/` devuelve conexión rechazada, el problema no está en el botón del panel: Guacamole no está escuchando realmente en `localhost:8080`.
- En ese caso revisa:
  - Docker Desktop arrancado
  - que `docker compose up -d` se haya ejecutado dentro de `infra/guacamole`
  - que exista `infra/guacamole/.env`
  - que el esquema se haya importado con `.\scripts\initdb.ps1`
- Cuando el panel muestre `Guacamole: No accesible`, esa comprobación sale de una sonda TCP a la URL configurada en `REMOTE_PC_GUACAMOLE_BASE_URL`.

## Cómo levantarlo dentro de WSL2

1. Habilita WSL y reinicia Windows:

```powershell
wsl --install -d Ubuntu
```

2. En Ubuntu (WSL), instala dependencias:

```bash
sudo apt update
sudo apt install -y php-cli php-mbstring php-xml php-curl php-sqlite3 php-zip unzip curl git openssh-client composer
```

3. Entra al proyecto:

```bash
cd /mnt/d/proyects/remote-control-pc/backend
```

4. Copia `.env` y ajusta rutas Linux:

```bash
cp .env.example .env
```

5. Si la clave va a vivir dentro de WSL, usa algo como:
   - `REMOTE_PC_SSH_PRIVATE_KEY_PATH=/home/tu_usuario/.ssh/conexion-server-pc`

6. Instala dependencias y arranca:

```bash
composer install
php artisan key:generate
php artisan config:clear
php artisan serve --host=0.0.0.0 --port=8000
```

7. Desde Windows puedes seguir consumiendo esa API en:
   - `http://127.0.0.1:8000`

Eso te permite probar Laravel en un entorno muy parecido al Ubuntu final, pero manteniendo el repo en `D:`.

### 4) Levantar Guacamole + MySQL (usuarios persistentes)

1. En PowerShell, entra en la carpeta:
   - `d:\proyects\remote-control-pc\infra\guacamole`
2. Crea el `.env`:
   - Copia `.env.example` a `.env`
   - Cambia `MYSQL_ROOT_PASSWORD` y `MYSQL_PASSWORD`
3. Inicializa el esquema de Guacamole en MySQL:
   - Ejecuta desde `PowerShell`:

```powershell
cd d:\proyects\remote-control-pc\infra\guacamole
Copy-Item .\.env.example .\.env
powershell -ExecutionPolicy Bypass -File .\scripts\initdb.ps1
```

   - Nota:
     - Si lanzas `initdb.ps1` desde `cmd`, Windows puede intentar abrirlo con el editor en vez de ejecutarlo.
     - La forma más segura en Windows es usar exactamente `powershell -ExecutionPolicy Bypass -File .\scripts\initdb.ps1`.

4. Levanta Guacamole:

```powershell
docker compose up -d
```

5. Abre Guacamole:
   - http://localhost:8080/guacamole
6. Credenciales iniciales:
   - usuario: `guacadmin`
   - password: `guacadmin`
7. Cambia la password en cuanto entres.

## Preparar el Windows destino (para RDP)

1. Activar Escritorio Remoto:
   - Configuración → Sistema → Escritorio remoto → Activar
2. Firewall:
   - Permitir RDP (TCP 3389) desde la IP del “servidor” (tu máquina/WSL/Ubuntu final).
3. Usuario:
   - Usa un usuario con contraseña (RDP no funciona bien con usuario sin password).

## Preparar el Windows destino (para SSH)

1. Instala OpenSSH Server:

```powershell
Add-WindowsCapability -Online -Name OpenSSH.Server~~~~0.0.1.0
```

2. Arranca el servicio y déjalo automático:

```powershell
Start-Service sshd
Set-Service -Name sshd -StartupType Automatic
```

3. Verifica o crea la regla de firewall:

```powershell
if (!(Get-NetFirewallRule -Name "OpenSSH-Server-In-TCP" -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -DisplayName 'OpenSSH Server (sshd)' -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22
}
```

4. Copia la clave pública del servidor/backend a `authorized_keys` del usuario remoto.
5. Prueba desde WSL:

```bash
ssh -i ~/.ssh/rcp_windows tu_usuario_windows@192.168.1.50 "echo ssh-ok"
```

## Crear conexión RDP en Guacamole

1. Entra a Guacamole → Settings → Connections → New Connection
2. Protocol: RDP
3. RDP settings:
   - Hostname: IP LAN del Windows destino (ej. `192.168.1.50`)
   - Port: `3389`
   - Username / Password: credenciales del Windows destino
4. Guarda y prueba la conexión.

### URL base vs conexión concreta

- `REMOTE_PC_GUACAMOLE_BASE_URL=http://localhost:8080/guacamole/`
  - abre la home de Guacamole.
  - desde ahí eliges manualmente la conexión RDP.
- `REMOTE_PC_GUACAMOLE_CONNECTION_ID=1` con `REMOTE_PC_GUACAMOLE_AUTH_PROVIDER=mysql`
  - hace que el backend construya una URL directa a esa conexión.
  - ejemplo resultante: `http://localhost:8080/guacamole/#/client/MQBjAG15c3Fs`
- `REMOTE_PC_GUACAMOLE_CLIENT_IDENTIFIER=...`
  - es la opción más robusta si ya tienes una conexión creada y quieres copiar exactamente el identificador que usa Guacamole.
  - basta con abrir esa conexión una vez en Guacamole y copiar lo que aparece tras `#/client/` en la URL del navegador.

## Mover Docker Desktop a D: en Windows

### Opción recomendada: mover el disk image de Docker Desktop

Esto es lo más útil si lo que más pesa son las imágenes, capas y almacenamiento interno de Docker.

1. Para los contenedores del proyecto:

```powershell
cd d:\proyects\remote-control-pc\infra\guacamole
docker compose down
```

2. Abre Docker Desktop.
3. Ve a:
   - `Settings` → `Resources` → `Advanced`
4. Busca:
   - `Disk image location`
5. Cambia la ruta a algo como:
   - `D:\DockerDesktop`
6. Aplica los cambios y deja que Docker Desktop mueva el almacenamiento.
7. Cuando termine, vuelve a levantar el stack:

```powershell
cd d:\proyects\remote-control-pc\infra\guacamole
docker compose up -d
```

8. Verifica:

```powershell
docker compose ps
curl.exe -I http://localhost:8080/guacamole/
```

Notas:
- Esto mueve el almacenamiento pesado de Docker Desktop, no solo este proyecto.
- Si tu versión de Docker Desktop ofrece un botón de `Move disk image`, úsalo.
- Haz este cambio con Docker Desktop abierto y sin contenedores importantes corriendo.

### Alternativa: mover solo los datos persistentes de este proyecto

Si no quieres mover todo Docker, puedes dejar las imágenes donde están y mover solo la persistencia de MySQL a `D:`.

En `infra/guacamole/docker-compose.yml`, podrías sustituir:

```yaml
volumes:
  - mysql_data:/var/lib/mysql
```

por algo como:

```yaml
volumes:
  - D:/docker-data/remote-control-pc/mysql:/var/lib/mysql
```

Pero para tu caso actual, la opción recomendada sigue siendo mover el `disk image` completo de Docker Desktop a `D:`.

## Tailscale en producción (VPN solo para la web/Guacamole)

### Qué significa “solo afecta a la web”

- Tailscale añade una interfaz de red adicional (normalmente `tailscale0` en Linux).
- No cambia SSH ni “secuestra” el tráfico del servidor por defecto.
- Para que solo Guacamole/Laravel queden “dentro de la VPN”, se hace una de estas dos cosas:
  - Opción A (simple): hacer que los servicios escuchen solo en la IP de Tailscale.
  - Opción B: escuchar en `0.0.0.0` pero filtrar con firewall permitiendo solo la interfaz Tailscale.

### Recomendación (opción A)

1. Instalar Tailscale en el Ubuntu final y autenticarse.
2. En `infra/guacamole/.env` del servidor Ubuntu:
   - `GUACAMOLE_BIND=<IP_TAILSCALE_DEL_UBUNTU>`
3. Reiniciar contenedores:

```bash
docker compose down
docker compose up -d
```

Así Guacamole solo queda accesible desde dispositivos dentro de Tailscale, mientras SSH puede seguir abierto como siempre.

## Replicar en Ubuntu final (servidor doméstico)

### 1) Base del sistema

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y ca-certificates curl gnupg ufw
```

### 2) Docker (solo para Guacamole)

```bash
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
"deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
$(. /etc/os-release && echo ${VERSION_CODENAME}) stable" | \
sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo usermod -aG docker $USER
```

### 3) Tailscale

```bash
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
tailscale ip -4
```

### 4) Guacamole

1. Copia esta carpeta `infra/guacamole` al Ubuntu.
2. Crea `.env` desde `.env.example` y ajusta passwords.
3. Inicializa BD (puedes ejecutar el import en bash; en Ubuntu es directo):

```bash
cd infra/guacamole
cp .env.example .env
set -a
. ./.env
set +a
docker compose up -d mysql
docker run --rm guacamole/guacamole:$GUACAMOLE_VERSION /opt/guacamole/bin/initdb.sh --mysql > initdb.sql
docker exec -i rcp-guac-mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < initdb.sql
docker compose up -d
```

4. Ajusta `GUACAMOLE_BIND` para que escuche solo en la IP de Tailscale del servidor.

## Backend Laravel actual

- Proyecto base creado en `backend/`
- Endpoint inicial implementado en `GET /api/pc/status`
- Wake-on-LAN implementado en `POST /api/pc/wake`
- Acciones por SSH implementadas en `POST /api/pc/shutdown` y `POST /api/pc/restart`
- Enlace a control remoto implementado en `GET /api/pc/control-link`
- Panel web implementado en `GET /` y `GET /dashboard`
- Configuración del PC remoto centralizada en `.env`
- Prueba SSH real usando el binario `ssh` del sistema

## Siguiente paso recomendado

- Añadir autenticación al backend
- Añadir autorización por roles y registro de acciones (audit log)
- Mejorar integración con Guacamole para abrir conexiones concretas
- Conectar la API con la UI y con Guacamole
