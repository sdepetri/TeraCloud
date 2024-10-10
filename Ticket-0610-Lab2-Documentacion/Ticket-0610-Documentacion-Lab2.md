# Ticket 0610 - Lab2
# Documentacion de Implementación: Infraestructura de Alta Disponibilidad en AWS

## Índice
1. [Configuración Inicial](#configuración-inicial)
2. [Creación de la Aplicación Web](#creación-de-la-aplicación-web)
3. [Configuración de ECR](#configuración-de-ecr)
4. [Configuración de ECS](#configuración-de-ecs)
5. [Configuración del Application Load Balancer](#configuración-del-application-load-balancer)
6. [Configuración de Route 53](#configuración-de-route-53)
7. [Configuración del Pipeline CI/CD](#configuración-del-pipeline-cicd)
8. [Pruebas y Verificación](#pruebas-y-verificación)

## 1. Configuración Inicial

### 1.1 Configuración de la cuenta AWS
- Inicia sesión en la consola de AWS  https://aws.amazon.com/.
- Asegúrate de tener los permisos necesarios para crear y gestionar recursos

### 1.2 Configuración de la CLI de AWS
```bash
aws configure
# Ingresa tu AWS Access Key ID
# Ingresa tu AWS Secret Access Key
# Ingresa la región por defecto (por ejemplo, us-east-1)
# Ingresa el formato de salida preferido (json)
```

## 2. Creación de la Aplicación Web

### 2.1 Crear el archivo index.php
Crea un nuevo archivo llamado `index.php` con el siguiente contenido:

```
<?php
$instance_id = gethostname();
echo "Hola mundo!! Servido desde la instancia: " . $instance_id;
?>
```
### 2.1.1 Alternativamente podemos crear un archivo que permita indica # de server

```<?php
// Inicializamos la semilla aleatoria con el tiempo actual
srand(time());
$instance_id = gethostname();
// Convertir el ID de la instancia en un número
$numeric_id = crc32($instance_id);
// Realizar el cálculo base para obtener un número entre 1 y 3
$base_result = ($numeric_id % 3) + 1;
// Generar un número aleatorio pequeño
$random_factor = rand(0, 10);
// Combinar el resultado base con el número aleatorio
$result = ($base_result + $random_factor) % 3 + 1;
echo "Hola mundo!! Servido desde la instancia: " . $instance_id . "\n";
echo "Servidor #: " . $result . "\n";
?>
```

### 2.2 Crear el Dockerfile
Crea un archivo llamado `Dockerfile` en el mismo directorio:
```Dockerfile
FROM php:7.4-apache
COPY index.php /var/www/html/
EXPOSE 80
```

## 3. Configuración de ECR

### 3.1 Crear un repositorio ECR
```bash
aws ecr create-repository --repository-name sd-pipeline-test
```
### 3.1.1 Versión grafica de consola
![Creacion repositorio EC#](./imagenes/crear-repo-ecr.png)

### 3.2 Autenticar Docker con ECR
```bash
aws ecr get-login-password --region us-east-2 | docker login --username AWS --password-stdin 253490770873.dkr.ecr.us-east-2.amazonaws.com
```

### 3.3 Construir y subir la imagen Docker
```bash
docker build -t sd-pipeline-test .
docker tag sd-pipeline-test:latest 253490770873.dkr.ecr.us-east-2.amazonaws.com/sd-pipeline-test:latest
docker push 253490770873.dkr.ecr.us-east-2.amazonaws.com/sd-pipeline-test:latest
```
### 3.3.1 Anexo: Construir la imagen Docker desde otras plataformas
```bash
docker buildx build --platform linux/amd64 -t sd-pipeline-test:latest .
```
## 4. Configuración de ECS

### 4.1 Crear un cluster ECS
```bash
aws ecs create-cluster --cluster-name sd-cluster-php
```
![Crear Cluster](./imagenes/crear_cluster_sd-cluster-php.png)
- Elige la capacidad deseada de escalado, setearemos 2 minimo y 3 como máximo
- Debes seleccionar las redes privadas para que no sean accesibles tus recursos desde fuera.
- Elige el Security group adecuado para tus instancias, o créalo en ese momento
- Agrega los Tags de tu proyecto


### 4.2 Crear una definición de tarea o Task definition

```json
{
    "family": "sd-task",
    "containerDefinitions": [
        {
            "name": "sd-container",
            "image": "253490770873.dkr.ecr.us-east-2.amazonaws.com/sd-pipeline-test:latest",
            "cpu": 0,
            "portMappings": [
                {
                    "containerPort": 80,
                    "hostPort": 0,
                    "protocol": "tcp"
                }
            ],
            "essential": true,
            "environment": [
                {
                    "name": "ENV",
                    "value": "dev"
                }
            ],
        }
    ],
    "executionRoleArn": "arn:aws:iam::253490770873:role/ECSTaskExecutionRoleLab",
    "networkMode": "bridge",
    "requiresCompatibilities": [
        "EC2"
    ],
    "cpu": "256",
    "memory": "512"
```



### 4.3 Crear un servicio ECS


![Crear servicio dentro del cluster](./imagenes/crear-un-nuevo-servicio-dentro-del-cluster.png)
![nombrar el servicio y la cantidad de task](./imagenes/nombrar-servicio.png)
![atachar Load Balancer al servicio](./imagenes/seleccionar-LB-del-servicio.png)
![nombrar el servicio y la cantidad de task](./imagenes/nombrar-servicio.png)
![atachar un nuevo cluster con el LB](./imagenes/crear-target-group-desde-servicio-para-LB.png)



## 5. Configuración del Application Load Balancer

### 5.1 Crear un Application Load Balancer

```bash
aws elbv2 create-load-balancer --name sdepetri-alb --subnets subnet-xxxxxxxx subnet-yyyyyyyy --security-groups sg-zzzzzzzz
```
![Crear servicio dentro del cluster](./imagenes/Listener_ECS_Screenshot%202024-10-10%20at%205.06.01 AM.png)
![Crear el ALB con dos reglas](./imagenes/ALBS_2024-10-10%20at%204.17.48 AM.png)
![nombrar el servicio y la cantidad de task](./imagenes/ALB_MAP_Screenshot%202024-10-10%20at%204.21.27 AM.png)
- Importante forward del puerto http al https, en puerto 443.
- Verificar la configuracion de certificados

### 5.2 Crear un Target Group
```bash
aws elbv2 create-target-group --name sdepetri-tg --protocol HTTP --port 80 --vpc-id vpc-xxxxxxxx --target-type ip
```
![Crear Listener en ALBr](./imagenes/Listener_ALB_Screenshot%202024-10-10%20at%205.12.46 AM.png)
![Crear ](./imagenes/TG-TargetGroup_Screenshot%202024-10-10%20at%204.28.56 AM.png)

### 5.3 Crear un Listener
```bash
aws elbv2 create-listener --load-balancer-arn <alb-arn> --protocol HTTPS --port 443 --certificates CertificateArn=<acm-certificate-arn> --default-actions Type=forward,TargetGroupArn=<target-group-arn>
```
### 5.4 Crear Security Group para EC2
![Crear servicio dentro del cluster](./imagenes/Security%20Group_Screenshot%202024-10-10%20at%204.53.59 AM.png)

## 6. Configuración de Route 53

### 6.1 Crear un registro A en Route 53
```bash
aws route53 change-resource-record-sets --hosted-zone-id <your-hosted-zone-id> --change-batch '{
  "Changes": [
    {
      "Action": "UPSERT",
      "ResourceRecordSet": {
        "Name": "www.sdepetri.site",
        "Type": "A",
        "AliasTarget": {
          "HostedZoneId": "<alb-hosted-zone-id>",
          "DNSName": "<alb-dns-name>",
          "EvaluateTargetHealth": true
        }
      }
    }
  ]
}'
```
![Atachar el ALB desde Hosted Zones](./imagenes/Hosted_Zones_Screenshot%202024-10-10%20at%204.58.04 AM.png)

## 7. Configuración del Pipeline CI/CD

### 7.0 Crear un repositorio en GitHub
```bash
aws codecommit create-repository --repository-name sd-repository-test
```

### 7.1 Crear Pipeline y conectar con GitHub
 - Indicar Repositorio y Rama

![Crear servicio dentro del cluster](./imagenes/Conectar_pipeline_con_Git_Screenshot%202024-10-10%20at%205.19.42 AM.png)

 - Indicar Ubuntu en el project

![Crear servicio dentro del cluster](./imagenes/Crear_Projet_CodeBuild_Screenshot%202024-10-10%20at%205.24.15 AM.png)

![Crear r](./imagenes/Privilege_flag_docker_project_codeBuild_Screenshot%202024-10-10%20at%205.28.13 AM.png)
 - Elegir las subnets privadas para desplegar las instancias
 - Podemos también en esta etapa crear las variables de entorno, o probarlas primero con path y luego poner variables.

![Crear r](./imagenes/Deploy_Stage_Screenshot%202024-10-10%20at%205.32.13 AM.png)


### 7.2 Configurar CodeBuild
Crea un archivo `buildspec.yml`:

```yaml
version: 0.2

phases:
  pre_build:
    commands:
      - echo Logging in to Amazon ECR...
      - aws ecr get-login-password --region us-east-2 | docker login --username AWS --password-stdin $ECR_REGISTRY
      - echo Getting the code version...
      - export CODE_VERSION=$(echo $CODEBUILD_RESOLVED_SOURCE_VERSION | cut -c 1-7)
  build:
    commands:
      - echo Building the Docker image...
      - docker build -t $ECR_REPOSITORY:${CODE_VERSION} .
      - docker tag $ECR_REPOSITORY:${CODE_VERSION} $ECR_REGISTRY/$ECR_REPOSITORY:${CODE_VERSION}
  post_build:
    commands:
      - echo Pushing the Docker image to ECR...
      - docker push $ECR_REGISTRY/$ECR_REPOSITORY:${CODE_VERSION}
      - echo Writing image definition file for ECS deployment...
      - printf '[{"name":"sd-container","imageUri":"%s"}]' $ECR_REGISTRY/$ECR_REPOSITORY:${CODE_VERSION} > imagedefinitions.json
artifacts:
  files:
    - imagedefinitions.json
```
### 7.2.1 Descripción
Este archivo buildspec.yml automatiza la creación y despliegue de imágenes Docker.

- Prepara el entorno: Se conecta a Amazon ECR y obtiene la última versión del código.
- Construye la imagen: Crea una imagen Docker basada en el código actualizado y la etiqueta con un identificador (versionado).
- Sube la imagen: Envía la imagen al registro de Amazon ECR.
- Prepara para el despliegue: Genera un archivo de configuración (imagedefinitions.json) que indica a otros servicios (como ECS) cómo usar esta imagen.
- Usa variables de entorno definidas en CodeBuild para proteger datos sensibles.

```bash
$ECR_REGISTRY/$ECR_REPOSITORY
```
![Crear servicio dentro del cluster](./imagenes/Variables_Entorno_Screenshot%202024-10-10%20at%204.35.53 AM.png)

Este proceso garantiza que cada cambio en el código resulte en una nueva imagen Docker almacenada de forma segura y lista para ser utilizada en aplicaciones.

- Verificar permisos del Role para CodeBuild
![Crear Role](./imagenes/Permisos_Code_Build_Screenshot%202024-10-10%20at%205.37.32 AM.png)
- Policy
![Crear ](./imagenes/Policy_token_role_Screenshot%202024-10-10%20at%205.40.45 AM.png)

### 7.2.2 Beneficios
- Automatización: Reduce errores manuales y agiliza el proceso de desarrollo.
- Versionamiento: Permite rastrear los cambios en el código y asociarlos a una 
- versión específica de la imagen.
- Escalabilidad: Facilita el despliegue de múltiples versiones de una aplicación.

este archivo es como una receta que automatiza la creación y despliegue de aplicaciones Docker en la nube de Amazon.

### 7.3 Crear el pipeline en CodePipeline
Usa la consola de AWS para crear un pipeline que incluya:
1. Fuente: Github (sdepetri/Teracloud)
2. Build: CodeBuild (usando el buildspec.yml)
3. Deploy: ECS (cluster y servicio creados anteriormente)

## 8. Sns Servicio de notificaciones
![Crear servicio dentro del cluster](./imagenes/SNS-everyOne-Screenshot%202024-10-10%20at%204.39.52 AM.png)
- Recordar al colocar el email confirmar desde la casilla la recepción del correo.

## 9. Pruebas y Verificación

1. Accede a https://www.sdepetri.site en tu navegador
2. Deberías ver el mensaje "Hola mundo!! Desde la instancia: [ID-INSTANCIA]"
3. Actualiza la página varias veces para verificar que las solicitudes se distribuyen entre las instancias

