FROM ipaya/php-7.0:cli-swoole-2.1

MAINTAINER Di Zhang <zhangdi_me@163.com>
LABEL maintainer="zhangdi_me@163.com"

ENV REDIS_HOST=127.0.0.1
ENV REDIS_PORT=6379
ENV APP_KEY=cron_api_app_key
ENV APP_SECRET=cron_api_app_secret
ENV API_URL=cron_api_url

WORKDIR /code
ADD . /code

RUN chmod +x /code/docker-entrypoint

ENTRYPOINT ["/code/docker-entrypoint"]
CMD ["start"]
