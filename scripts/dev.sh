#!/usr/bin/env bash
# SpaceRent - jalankan backend (Laravel), frontend (Vite) dan scheduler dengan satu perintah.
#
#   bash scripts/dev.sh            # = start: setup otomatis, build frontend, jalankan semua (bisa dibuka dari HP)
#   bash scripts/dev.sh hmr        # frontend mode Vite dev (hot reload, hanya untuk laptop)
#   bash scripts/dev.sh status     # cek apa yang sedang berjalan + alamat untuk HP
#   bash scripts/dev.sh stop       # hentikan semuanya
#   bash scripts/dev.sh setup      # hanya siapkan (composer, npm, .env, migrasi, seed, build)
#   bash scripts/dev.sh test       # jalankan seluruh test
#   bash scripts/dev.sh reset      # HAPUS database lalu isi ulang data demo (minta konfirmasi)
#
# Ubah port: PORT=9000 VITE_PORT=5174 bash scripts/dev.sh

set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1

PORT="${PORT:-8000}"
VITE_PORT="${VITE_PORT:-5173}"
CMD="${1:-start}"
PID_DIR="storage/dev-pids"
LOG_DIR="storage/logs"

G=$'\033[32m'; Y=$'\033[33m'; R=$'\033[31m'; B=$'\033[1m'; N=$'\033[0m'
ok()   { echo "${G}[ok]${N} $*"; }
warn() { echo "${Y}[!]${N} $*"; }
err()  { echo "${R}[x]${N} $*" >&2; }
step() { echo; echo "${B}==> $*${N}"; }

is_windows() { case "$(uname -s)" in MINGW*|MSYS*|CYGWIN*) return 0 ;; *) return 1 ;; esac; }

lan_ip() {
  if is_windows; then
    ipconfig 2>/dev/null | tr -d '\r' | grep -i 'IPv4' | grep -oE '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' \
      | grep -vE '^(127\.|169\.254\.|192\.168\.56\.)' | head -1
  else
    (hostname -I 2>/dev/null | awk '{print $1}') || true
  fi
}

port_pid() {
  local p="$1"
  if is_windows; then
    netstat -ano 2>/dev/null | tr -d '\r' | awk -v p=":$p" '$4=="LISTENING" && $2 ~ (p "$") {print $5; exit}'
  else
    lsof -ti "tcp:$p" -sTCP:LISTEN 2>/dev/null | head -1
  fi
}

kill_port() {
  local pid; pid="$(port_pid "$1")"
  [ -z "$pid" ] && return 0
  if is_windows; then taskkill //F //T //PID "$pid" >/dev/null 2>&1; else kill "$pid" 2>/dev/null; fi
  ok "port $1 dihentikan (PID $pid)"
}

need() { command -v "$1" >/dev/null 2>&1 || { err "'$1' belum terpasang. $2"; exit 1; }; }

check_tools() {
  need php "Pasang PHP 8.3+ dan pastikan ada di PATH."
  need composer "Pasang Composer: https://getcomposer.org"
  need node "Pasang Node.js 20+: https://nodejs.org"
  need npm "npm ikut terpasang bersama Node.js."
}

do_setup() {
  check_tools
  step "Menyiapkan proyek"
  if [ ! -f .env ]; then cp .env.example .env && ok ".env dibuat dari .env.example"; fi
  [ -d vendor ] || { step "composer install"; composer install --no-interaction || exit 1; }
  grep -qE '^APP_KEY=.+' .env || { php artisan key:generate --force >/dev/null && ok "APP_KEY dibuat"; }
  [ -f database/database.sqlite ] || { touch database/database.sqlite && ok "database SQLite dibuat"; }
  [ -d node_modules ] || { step "npm install"; npm install || exit 1; }
  php artisan migrate --force >/dev/null && ok "migrasi selesai"
  local n; n="$(php artisan tinker --execute='echo App\Models\Space::count();' 2>/dev/null | tail -1 | tr -dc '0-9')"
  if [ "${n:-0}" = "0" ]; then php artisan db:seed --force >/dev/null && ok "data demo diisi (ruangan, akun, ulasan)"; else ok "data sudah ada ($n ruangan)"; fi
  [ -e public/storage ] || { php artisan storage:link >/dev/null 2>&1 && ok "storage:link"; }
  mkdir -p "$PID_DIR" "$LOG_DIR"
  if ! grep -qE '^MIDTRANS_SERVER_KEY=.+' .env; then warn "MIDTRANS_SERVER_KEY kosong: tombol Bayar tidak akan berfungsi (lihat docs/PANDUAN.md bagian .env)."; fi
}

build_assets() {
  step "Build frontend (Vite)"
  npm run build >"$LOG_DIR/dev-build.log" 2>&1 && ok "public/build siap" || { err "build gagal, lihat $LOG_DIR/dev-build.log"; tail -15 "$LOG_DIR/dev-build.log"; exit 1; }
}

start_bg() { # nama perintah...
  local name="$1"; shift
  "$@" >"$LOG_DIR/dev-$name.log" 2>&1 &
  echo $! >"$PID_DIR/$name.pid"
  ok "$name berjalan (log: $LOG_DIR/dev-$name.log)"
}

print_info() {
  local ip; ip="$(lan_ip)"
  echo
  echo "${B}================ SpaceRent siap ================${N}"
  echo " Laptop   : http://localhost:$PORT"
  [ -n "$ip" ] && echo " HP (Wi-Fi sama): http://$ip:$PORT" || echo " HP: IP LAN tidak terdeteksi (jalankan ipconfig)"
  echo " Admin    : http://localhost:$PORT/admin/login"
  [ -f public/hot ] && echo " Vite HMR : http://localhost:$VITE_PORT (hanya laptop)"
  echo
  echo " Akun demo (kata sandi lokal, ganti sebelum produksi):"
  echo "   Pembeli : pembeli@spacerent.id / pembeli12345"
  echo "   Penjual : penjual@spacerent.id / penjual12345"
  echo "   Admin   : admin@spacerent.id   / admin12345   (login di /admin/login)"
  echo
  echo " Panduan lengkap: docs/PANDUAN.md"
  echo " Hentikan: tekan Ctrl+C, atau 'bash scripts/dev.sh stop' dari terminal lain"
  echo "${B}================================================${N}"
}

do_stop() {
  step "Menghentikan SpaceRent"
  for f in "$PID_DIR"/*.pid; do
    [ -e "$f" ] || continue
    kill "$(cat "$f")" 2>/dev/null; rm -f "$f"
  done
  kill_port "$PORT"
  # Vite hanya dimatikan bila itu milik proyek ini (public/hot ada); jangan menyentuh proses node lain.
  [ -f public/hot ] && kill_port "$VITE_PORT"
  rm -f public/hot
  ok "selesai"
}

do_status() {
  local ip; ip="$(lan_ip)"
  step "Status"
  [ -n "$(port_pid "$PORT")" ] && ok "backend  : berjalan di port $PORT" || warn "backend  : mati"
  [ -f public/hot ] && ok "vite dev : berjalan (HMR) di port $VITE_PORT" || echo "   vite dev : tidak aktif (normal: frontend memakai public/build)"
  [ -f public/build/manifest.json ] && ok "frontend : public/build ada" || warn "frontend : belum di-build (jalankan: npm run build)"
  for n in scheduler queue; do
    if [ -f "$PID_DIR/$n.pid" ] && kill -0 "$(cat "$PID_DIR/$n.pid")" 2>/dev/null; then ok "$n : berjalan"; else echo "   $n : tidak berjalan lewat skrip ini"; fi
  done
  [ -n "$ip" ] && echo "   Alamat HP: http://$ip:$PORT"
  local pid; pid="$(port_pid "$PORT")"
  if is_windows && [ -n "$pid" ]; then
    netstat -ano 2>/dev/null | tr -d '\r' | awk -v p=":$PORT" '$4=="LISTENING" && $2 ~ (p "$") {print $2}' | grep -q '^127\.0\.0\.1' \
      && warn "backend hanya mendengarkan 127.0.0.1: HP tidak bisa akses. Jalankan 'bash scripts/dev.sh stop' lalu 'bash scripts/dev.sh'."
  fi
}

do_start() {
  local mode="${1:-build}"
  do_setup
  if [ "$mode" = "hmr" ]; then
    rm -f public/build/hot
    step "Frontend: Vite dev server (HMR)"
    if [ -n "$(port_pid "$VITE_PORT")" ]; then warn "port $VITE_PORT sudah dipakai, dilewati"; else start_bg vite npm run dev -- --host --port "$VITE_PORT"; fi
  else
    build_assets
  fi

  step "Backend: Laravel di 0.0.0.0:$PORT"
  if [ -n "$(port_pid "$PORT")" ]; then
    warn "port $PORT sudah dipakai (mungkin sudah berjalan). Pakai 'bash scripts/dev.sh stop' dulu bila ingin memulai ulang."
  else
    start_bg serve php artisan serve --host=0.0.0.0 --port="$PORT"
  fi
  start_bg scheduler php artisan schedule:work
  start_bg queue php artisan queue:work --tries=1 --sleep=3

  sleep 2
  print_info
  trap 'echo; do_stop; exit 0' INT TERM
  # tahan terminal tetap terbuka; Ctrl+C menghentikan semuanya
  while true; do sleep 3600 & wait $!; done
}

case "$CMD" in
  start|run|"") do_start build ;;
  hmr|dev)      do_start hmr ;;
  setup)        do_setup && build_assets && ok "siap. Jalankan: bash scripts/dev.sh" ;;
  stop)         do_stop ;;
  status)       do_status ;;
  test)         check_tools; php artisan test ;;
  reset)
    read -r -p "Ini MENGHAPUS seluruh data database dan mengisi ulang data demo. Ketik 'ya' untuk lanjut: " a
    [ "$a" = "ya" ] && { php artisan migrate:fresh --seed --force; } || echo "dibatalkan" ;;
  help|-h|--help) sed -n '2,13p' "$0" ;;
  *) err "perintah tidak dikenal: $CMD"; sed -n '2,13p' "$0"; exit 1 ;;
esac
