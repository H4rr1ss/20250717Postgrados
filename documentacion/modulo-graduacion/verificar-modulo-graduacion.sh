#!/bin/bash

# ============================================================
# Script de Verificación del Módulo de Graduación
# ============================================================
# Fecha: 11 de julio de 2026
# Propósito: Verificar que todos los requisitos iniciales
#            del módulo de graduación están correctamente
#            configurados.
# ============================================================

echo "🎓 ============================================================"
echo "🎓 VERIFICACIÓN DEL MÓDULO DE GRADUACIÓN"
echo "🎓 ============================================================"
echo ""

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

# ============================================================
# Función para verificar
# ============================================================
check_pass() {
    echo -e "${GREEN}✅ PASS:${NC} $1"
}

check_fail() {
    echo -e "${RED}❌ FAIL:${NC} $1"
    ((ERRORS++))
}

check_warn() {
    echo -e "${YELLOW}⚠️  WARN:${NC} $1"
    ((WARNINGS++))
}

# ============================================================
# 1. VERIFICAR CARPETAS
# ============================================================
echo "📁 Verificando estructura de carpetas..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if docker-compose exec -T web test -d /var/www/data/graduacion; then
    check_pass "Carpeta base data/graduacion/ existe"
else
    check_fail "Carpeta base data/graduacion/ NO existe"
fi

if docker-compose exec -T web test -d /var/www/data/graduacion/plantillas/carta-examinadores; then
    check_pass "Carpeta plantillas/carta-examinadores/ existe"
else
    check_fail "Carpeta plantillas/carta-examinadores/ NO existe"
fi

if docker-compose exec -T web test -d /var/www/data/graduacion/procesos; then
    check_pass "Carpeta procesos/ existe"
else
    check_fail "Carpeta procesos/ NO existe"
fi

if docker-compose exec -T web test -d /var/www/data/graduacion/global/documentos-soporte; then
    check_pass "Carpeta global/documentos-soporte/ existe"
else
    check_fail "Carpeta global/documentos-soporte/ NO existe"
fi

if docker-compose exec -T web test -d /var/www/data/graduacion/global/cartas-descarga; then
    check_pass "Carpeta global/cartas-descarga/ existe"
else
    check_fail "Carpeta global/cartas-descarga/ NO existe"
fi

if docker-compose exec -T web test -d /var/www/data/graduacion/global/requisitos-apoyo; then
    check_pass "Carpeta global/requisitos-apoyo/ existe"
else
    check_fail "Carpeta global/requisitos-apoyo/ NO existe"
fi

echo ""

# ============================================================
# 2. VERIFICAR ARCHIVOS OBLIGATORIOS
# ============================================================
echo "📄 Verificando archivos obligatorios..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if docker-compose exec -T web test -f /var/www/data/graduacion/plantillas/carta-examinadores/general.docx; then
    check_pass "Plantilla general.docx existe"
else
    check_fail "Plantilla general.docx NO existe (CRÍTICO)"
fi

if docker-compose exec -T web test -f /var/www/public/img/email-footer.jpg; then
    check_pass "Imagen email-footer.jpg existe"
else
    check_warn "Imagen email-footer.jpg NO existe (correos sin footer)"
fi

echo ""

# ============================================================
# 3. VERIFICAR PERMISOS
# ============================================================
echo "🔐 Verificando permisos..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

OWNER=$(docker-compose exec -T web stat -c '%U:%G' /var/www/data/graduacion 2>/dev/null)
if [ "$OWNER" == "www-data:www-data" ]; then
    check_pass "Propietario correcto: www-data:www-data"
else
    check_warn "Propietario incorrecto: $OWNER (esperado: www-data:www-data)"
fi

PERMS=$(docker-compose exec -T web stat -c '%a' /var/www/data/graduacion 2>/dev/null)
if [ "$PERMS" == "755" ] || [ "$PERMS" == "775" ]; then
    check_pass "Permisos correctos: $PERMS"
else
    check_warn "Permisos: $PERMS (recomendado: 755)"
fi

echo ""

# ============================================================
# 4. VERIFICAR TABLAS DE BASE DE DATOS
# ============================================================
echo "🗄️  Verificando tablas de base de datos..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

TABLES_COUNT=$(docker-compose exec -T db mysql -u user -ppassword db_postgrados -N -e "
SELECT COUNT(*) 
FROM information_schema.tables 
WHERE table_schema = 'db_postgrados' 
AND table_name LIKE 'examen_%';" 2>/dev/null)

if [ "$TABLES_COUNT" -ge 23 ]; then
    check_pass "Tablas del módulo: $TABLES_COUNT (esperado: 23+)"
else
    check_fail "Tablas del módulo: $TABLES_COUNT (esperado: 23+)"
fi

# Verificar tablas críticas
CRITICAL_TABLES=(
    "examen_tipo"
    "examen_paso_catalogo"
    "examen_proceso"
    "examen_terna"
    "examen_documento"
    "archivo_local"
    "examen_autorizacion_config"
)

for table in "${CRITICAL_TABLES[@]}"; do
    if docker-compose exec -T db mysql -u user -ppassword db_postgrados -e "DESCRIBE $table;" &>/dev/null; then
        check_pass "Tabla $table existe"
    else
        check_fail "Tabla $table NO existe"
    fi
done

# Verificar tablas de matriz
MATRIZ_COUNT=$(docker-compose exec -T db mysql -u user -ppassword db_postgrados -N -e "
SELECT COUNT(*) 
FROM information_schema.tables 
WHERE table_schema = 'db_postgrados' 
AND table_name LIKE 'examen_matriz_%';" 2>/dev/null)

if [ "$MATRIZ_COUNT" -ge 4 ]; then
    check_pass "Tablas de matriz: $MATRIZ_COUNT (esperado: 4)"
else
    check_fail "Tablas de matriz: $MATRIZ_COUNT (esperado: 4)"
fi

echo ""

# ============================================================
# 5. VERIFICAR DATOS SEMILLA
# ============================================================
echo "🌱 Verificando datos semilla..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Verificar pasos
PASOS_COUNT=$(docker-compose exec -T db mysql -u user -ppassword db_postgrados -N -e "
SELECT COUNT(*) FROM examen_paso_catalogo WHERE activo = 1;" 2>/dev/null)

if [ "$PASOS_COUNT" -ge 11 ]; then
    check_pass "Pasos configurados: $PASOS_COUNT (esperado: 11)"
else
    check_warn "Pasos configurados: $PASOS_COUNT (esperado: 11)"
fi

# Verificar tipos de examen
TIPOS_COUNT=$(docker-compose exec -T db mysql -u user -ppassword db_postgrados -N -e "
SELECT COUNT(*) FROM examen_tipo WHERE activo = 1;" 2>/dev/null)

if [ "$TIPOS_COUNT" -ge 3 ]; then
    check_pass "Tipos de examen: $TIPOS_COUNT (esperado: 3+)"
else
    check_warn "Tipos de examen: $TIPOS_COUNT (esperado: 3+)"
fi

# Verificar rol Secretario
ROL_EXISTS=$(docker-compose exec -T db mysql -u user -ppassword db_postgrados -N -e "
SELECT COUNT(*) FROM rol WHERE cod_rol = 11;" 2>/dev/null)

if [ "$ROL_EXISTS" -eq 1 ]; then
    check_pass "Rol 'Secretario de Examen Privado' existe"
else
    check_fail "Rol 'Secretario de Examen Privado' NO existe"
fi

# Verificar usuario secretario
USER_EXISTS=$(docker-compose exec -T db mysql -u user -ppassword db_postgrados -N -e "
SELECT COUNT(*) FROM usuario WHERE cod_rol = 11;" 2>/dev/null)

if [ "$USER_EXISTS" -ge 1 ]; then
    check_pass "Usuario con rol Secretario existe"
else
    check_warn "Usuario con rol Secretario NO existe"
fi

# Verificar matrices de evaluación
MATRICES_COUNT=$(docker-compose exec -T db mysql -u user -ppassword db_postgrados -N -e "
SELECT COUNT(*) FROM examen_matriz_tipo WHERE activo = 1;" 2>/dev/null)

if [ "$MATRICES_COUNT" -ge 20 ]; then
    check_pass "Matrices de evaluación: $MATRICES_COUNT (esperado: 20)"
else
    check_warn "Matrices de evaluación: $MATRICES_COUNT (esperado: 20)"
fi

# Verificar configuración global del paso 6
CONFIG_EXISTS=$(docker-compose exec -T db mysql -u user -ppassword db_postgrados -N -e "
SELECT COUNT(*) FROM examen_autorizacion_config WHERE cod_config = 1;" 2>/dev/null)

if [ "$CONFIG_EXISTS" -eq 1 ]; then
    check_pass "Configuración global del paso 6 existe"
else
    check_warn "Configuración global del paso 6 NO existe"
fi

echo ""

# ============================================================
# 6. VERIFICAR DEPENDENCIAS PHP
# ============================================================
echo "🐘 Verificando dependencias PHP..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if docker-compose exec -T web test -d /var/www/vendor/zendframework/zend-mail; then
    check_pass "zend-mail instalado"
else
    check_fail "zend-mail NO instalado (ejecutar: composer install)"
fi

if docker-compose exec -T web test -d /var/www/vendor/phpoffice/phpword; then
    check_pass "phpoffice/phpword instalado"
else
    check_warn "phpoffice/phpword NO instalado (puede ser necesario)"
fi

echo ""

# ============================================================
# 7. VERIFICAR ARCHIVOS PHP CRÍTICOS
# ============================================================
echo "💻 Verificando archivos PHP críticos..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

CRITICAL_PHP_FILES=(
    "module/Eep/src/Controller/ExamenController.php"
    "module/Eep/src/Controller/StudentGraduationController.php"
    "module/Eep/src/Service/ExamenManager.php"
    "module/Eep/src/Service/StudentGraduationManager.php"
    "module/Eep/src/Service/CartaExaminadoresManager.php"
    "module/Eep/src/Service/CartaGenerator.php"
    "module/Eep/src/Service/AutorizacionImpresionManager.php"
    "module/Eep/src/Service/MailManager.php"
)

for file in "${CRITICAL_PHP_FILES[@]}"; do
    if docker-compose exec -T web test -f "/var/www/$file"; then
        check_pass "$(basename $file)"
    else
        check_fail "$(basename $file) NO existe"
    fi
done

echo ""

# ============================================================
# 8. VERIFICAR CONFIGURACIÓN SMTP
# ============================================================
echo "📧 Verificando configuración SMTP..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if docker-compose exec -T web test -f /var/www/config/autoload/local.php; then
    if docker-compose exec -T web grep -q "smtp" /var/www/config/autoload/local.php 2>/dev/null; then
        check_pass "Configuración SMTP presente en local.php"
    else
        check_warn "Configuración SMTP NO encontrada en local.php"
    fi
else
    check_warn "Archivo config/autoload/local.php NO existe"
fi

echo ""

# ============================================================
# RESUMEN FINAL
# ============================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 RESUMEN DE VERIFICACIÓN"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}🎉 ¡TODO CORRECTO!${NC}"
    echo "El módulo de graduación está completamente configurado."
    echo ""
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠️  CONFIGURACIÓN PARCIAL${NC}"
    echo "Errores críticos: $ERRORS"
    echo "Advertencias: $WARNINGS"
    echo ""
    echo "El módulo funcionará pero con limitaciones menores."
    echo ""
    exit 0
else
    echo -e "${RED}❌ CONFIGURACIÓN INCOMPLETA${NC}"
    echo "Errores críticos: $ERRORS"
    echo "Advertencias: $WARNINGS"
    echo ""
    echo "Por favor, revisa los errores marcados arriba antes de usar el módulo."
    echo ""
    echo "📚 Consulta: MODULO_GRADUACION_REQUISITOS_INICIALES.md"
    echo ""
    exit 1
fi
