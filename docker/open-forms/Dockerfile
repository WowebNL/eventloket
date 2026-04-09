FROM openformulieren/open-forms:3.3.9

USER root
COPY openforms-eventloket-auth /tmp/eventloket-auth
RUN pip install /tmp/eventloket-auth
USER maykin
