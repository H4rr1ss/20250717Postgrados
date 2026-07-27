#!/bin/bash

# ============================================================
# Script de Inicialización del Módulo de Graduación
# ============================================================
# Fecha: 11 de julio de 2026
# Propósito: Crear todas las carpetas y archivos necesarios
#            para el módulo de graduación desde cero.
# ============================================================

echo "🎓 ============================================================"
echo "🎓 INICIALIZACIÓN DEL MÓDULO DE GRADUACIÓN"
echo "🎓 ============================================================"
echo ""

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# ============================================================
# 1. CREAR ESTRUCTURA DE CARPETAS
# ============================================================
echo "📁 Creando estructura de carpetas..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

docker-compose exec web mkdir -p /var/www/data/graduacion/plantillas/carta-examinadores
docker-compose exec web mkdir -p /var/www/data/graduacion/procesos
docker-compose exec web mkdir -p /var/www/data/graduacion/global/documentos-soporte
docker-compose exec web mkdir -p /var/www/data/graduacion/global/cartas-descarga
docker-compose exec web mkdir -p /var/www/data/graduacion/global/requisitos-apoyo

echo -e "${GREEN}✅${NC} Estructura de carpetas creada"
echo ""

# ============================================================
# 2. ESTABLECER PERMISOS
# ============================================================
echo "🔐 Configurando permisos..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

docker-compose exec web chown -R www-data:www-data /var/www/data/graduacion
docker-compose exec web chmod -R 755 /var/www/data/graduacion

echo -e "${GREEN}✅${NC} Permisos configurados (www-data:www-data, 755)"
echo ""

# ============================================================
# 3. CREAR ARCHIVO README EN PLANTILLAS
# ============================================================
echo "📝 Creando archivo README en plantillas..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

docker-compose exec web bash -c 'cat > /var/www/data/graduacion/plantillas/carta-examinadores/README.md << "EOL"
# Plantillas — Carta de Examinadores

Esta carpeta contiene las plantillas .docx usadas por CartaGenerator.php
para generar cartas de examinadores del paso 5 del proceso de graduación.

## Archivo Obligatorio

**general.docx** — Plantilla principal con placeholders:
- ${estudiante_nombre}
- ${estudiante_carnet}
- ${estudiante_cui}
- ${titulo_trabajo}
- ${tipo_examen}
- ${fecha_examen}
- ${hora_examen}
- ${asesor_nombre}
- ${examinador_1_nombre}
- ${examinador_1_colegiado}
- ${examinador_2_nombre}
- ${examinador_2_colegiado}
- ${examinador_3_nombre}
- ${examinador_3_colegiado}
- ${coordinador_nombre}
- ${fecha_emision_carta}

## IMPORTANTE

Si este archivo no existe, el paso 5 (Carta de Examinadores) NO funcionará.
EOL
'

echo -e "${GREEN}✅${NC} README creado"
echo ""

# ============================================================
# 4. INSTALAR BASE DE DATOS
# ============================================================


# ============================================================
# 5. INSTALAR DEPENDENCIAS PHP
# ============================================================


# ============================================================
# 6. CONFIGURAR SMTP
# ============================================================


# ============================================================
# 7. REINICIAR SERVICIOS
# ============================================================


# ============================================================
# RESUMEN FINAL
# ============================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ INICIALIZACIÓN COMPLETADA"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📝 Tareas pendientes:"
echo ""
echo "1. ⚠️  CRÍTICO: Copiar/crear el archivo:"
echo "   data/graduacion/plantillas/carta-examinadores/general.docx"
echo ""
echo "2. Verificar que existe: public/img/email-footer.jpg"
echo ""
echo "3. Si configuraste SMTP, editar: config/autoload/local.php"
echo ""
echo "4. Ejecutar script de verificación:"
echo "   ./verificar-modulo-graduacion.sh"
echo ""
echo "5. Acceder al sistema:"
echo "   URL: http://localhost:8080"
echo "   Usuario: secretario.examen@farusac.edu.gt"
echo "   Contraseña: PostgradosUsac2024"
echo ""
echo "📚 Documentación completa:"
echo "   MODULO_GRADUACION_REQUISITOS_INICIALES.md"
echo ""
