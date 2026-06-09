The following are some helper files for DDEV that might make local development easier to set up.

To set up DDEV on your machine, follow the instructions provided [here](https://ddev.readthedocs.io/en/stable/users/install/). For further details on setting up and running Drupal contrib projects with DDEV, refer to the documentation [here](https://github.com/ddev/ddev-drupal-contrib).

## Streaming

To get streaming of responses to work on different platforms, the following might need to be set depending on whether you use Apache or nginx. Note that these settings are not good for scaling.

### Apache

Copy the file `docs/ddev-examples/apache-streaming.conf` to your `.ddev/apache` and run `ddev restart`.

### nginx

Copy the file `docs/ddev-examples/nginx-site.conf` to your `.ddev/nginx_full` and run `ddev restart`.

## Services

### Ollama

[Ollama](https://ollama.com/) is a model provider that you can run locally. It's not really recommended to run in ddev since you usually want to share the host resource. To set up Ollama on your host machine and learn how to connect it, you can review the [Ollama Provider module](https://www.drupal.org/project/ai_provider_ollama).

If you however want to set up Ollama via DDEV, there are files under `docs/ddev-examples` that you can use. Copy the `docker-compose.ollama.yaml` to your `.ddev` directory if you run CPU or the file `docker-compose.ollama-gpu.yaml` to your `.ddev` directory if you want to run with an nvidia GPU. Then run `ddev restart`. The machine can be connected to on `http://ollama:11434` or `http://ollama-gpu:11434`.

### Milvus

[Milvus](https://milvus.io/) is a vector database that can be used together with AI Search. To set it up locally, use the following file.

To find information on how to set it up, review the [Milvus module](https://git.drupalcode.org/project/ai_vdb_provider_milvus).

This will expose milvus internally on `http://milvus:19530` and a GUI on `{sitename}.ddev.site:8521`.

### Mockoon
[Mockoon](https://mockoon.com/) is a service that can replicate certain repos. We currently use it for kernel and browser testing to avoid having to pay services to test them.

There is a Mockoon file for this under `tests/assets/mockoon/`. If you want to run this you can use the following file.

docker-compose.mockoon.yaml
```yaml
services:
  mockoon:
    container_name: ddev-${DDEV_SITENAME}-mockoon
    image: mockoon/cli:latest
    environment:
      - HTTP_EXPOSE=3010:3010
      - HTTPS_EXPOSE=3010:3010
      - VIRTUAL_HOST=$DDEV_HOSTNAME
      - MOCKOON_BASEHOST=http://mockoon:3010
    command: [ "--data", "/data/openai.json", "--port", "3010" ]
    volumes:
      - ../web/modules/custom/ai/tests/assets/mockoon/:/data/:readonly
    labels:
      com.ddev.site-name: ${DDEV_SITENAME}

```
