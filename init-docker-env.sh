#!/bin/bash

# Script de inicialización del entorno de desarrollo Docker
# Archivo: init-docker-env.sh

echo "🐳 Inicializando entorno de desarrollo con Docker..."

# Verificar que Docker y Docker Compose están disponibles
if ! command -v docker &> /dev/null; then
    echo "❌ Docker no está instalado o no está disponible"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose no está instalado o no está disponible"
    exit 1
fi

# Parar contenedores existentes si están corriendo
echo "🛑 Deteniendo contenedores existentes..."
docker-compose down

# Construir y levantar los contenedores
echo "🏗️  Construyendo y levantando contenedores..."
docker-compose up -d --build

# Esperar a que la base de datos esté lista
echo "⏳ Esperando que la base de datos esté lista..."
sleep 30

# Instalar dependencias de Composer si no existen
if [ ! -d "vendor" ]; then
    echo "📦 Instalando dependencias de Composer..."
    docker-compose exec web composer install --no-dev --optimize-autoloader
fi

# Verificar permisos de directorio de sesiones
echo "🔐 Configurando permisos de directorio de sesiones..."
docker-compose exec web chown -R www-data:www-data /var/www/data
docker-compose exec web chmod -R 1733 /var/www/data/sessiones

# Verificar que la base de datos se importó correctamente
echo "🗄️  Verificando base de datos..."
DB_CHECK=$(docker-compose exec db mysql -u user -ppassword -e "USE db_postgrados; SHOW TABLES;" 2>/dev/null | wc -l)
if [ "$DB_CHECK" -gt 1 ]; then
    echo "✅ Base de datos configurada correctamente"
else
    echo "ℹ️  Base de datos vacía - se inicializará automáticamente con el SQL disponible"
fi

echo "🚀 Entorno listo!"
echo "📱 Aplicación disponible en: http://localhost:8080"
echo "🗄️  Base de datos disponible en puerto: 3307"
echo ""
echo "Comandos útiles:"
echo "  docker-compose logs -f web    # Ver logs del contenedor web"
echo "  docker-compose logs -f db     # Ver logs de la base de datos"
echo "  docker-compose exec web bash  # Acceder al contenedor web"
echo "  docker-compose down           # Detener todos los contenedores"
