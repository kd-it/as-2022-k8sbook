FROM python:3.9
RUN apt-get update; \
    apt-get install -y locales; \
    echo "ja_JP.UTF-8 UTF-8" >> /etc/locale.gen; \
    locale-gen
ENV LC_ALL=ja_JP.UTF-8
RUN pip install jupyter-book; pip cache purge
WORKDIR /work
