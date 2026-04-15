FROM openformulieren/open-forms:3.3.9

USER root
COPY openforms-eventloket-auth /tmp/eventloket-auth
COPY openforms-eventloket-prefill /tmp/eventloket-prefill
RUN pip install /tmp/eventloket-auth /tmp/eventloket-prefill
USER maykin
