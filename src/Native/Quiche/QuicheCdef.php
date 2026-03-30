<?php declare(strict_types=1);

namespace PhpTuic\Native\Quiche;

final class QuicheCdef
{
    public static function definitions(): string
    {
        return <<<'CDEF'
typedef unsigned char bool;
typedef unsigned char uint8_t;
typedef signed char int8_t;
typedef unsigned short uint16_t;
typedef short int16_t;
typedef unsigned int uint32_t;
typedef int int32_t;
typedef unsigned long long uint64_t;
typedef long long int64_t;
typedef unsigned long long size_t;
typedef long long ssize_t;
typedef unsigned int socklen_t;
typedef unsigned short sa_family_t;

struct sockaddr {
    sa_family_t sa_family;
    char sa_data[14];
};

struct in_addr {
    uint32_t s_addr;
};

struct in6_addr {
    uint8_t s6_addr[16];
};

struct sockaddr_in {
    sa_family_t sin_family;
    uint16_t sin_port;
    struct in_addr sin_addr;
    char sin_zero[8];
};

struct sockaddr_in6 {
    sa_family_t sin6_family;
    uint16_t sin6_port;
    uint32_t sin6_flowinfo;
    struct in6_addr sin6_addr;
    uint32_t sin6_scope_id;
};

struct sockaddr_storage {
    sa_family_t ss_family;
    char __ss_padding[126];
};

struct timespec {
    int64_t tv_sec;
    int64_t tv_nsec;
};

typedef struct quiche_config quiche_config;
typedef struct quiche_conn quiche_conn;
typedef struct quiche_stream_iter quiche_stream_iter;

typedef struct {
    struct sockaddr *from;
    socklen_t from_len;
    struct sockaddr *to;
    socklen_t to_len;
} quiche_recv_info;

typedef struct {
    struct sockaddr_storage from;
    socklen_t from_len;
    struct sockaddr_storage to;
    socklen_t to_len;
    struct timespec at;
} quiche_send_info;

const char *quiche_version(void);
quiche_config *quiche_config_new(uint32_t version);
void quiche_config_free(quiche_config *config);
void quiche_config_verify_peer(quiche_config *config, bool v);
void quiche_config_log_keys(quiche_config *config);
int quiche_config_set_application_protos(quiche_config *config, const uint8_t *protos, size_t protos_len);
void quiche_config_set_max_idle_timeout(quiche_config *config, uint64_t v);
void quiche_config_set_max_recv_udp_payload_size(quiche_config *config, size_t v);
void quiche_config_set_max_send_udp_payload_size(quiche_config *config, size_t v);
void quiche_config_set_initial_max_data(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_stream_data_bidi_local(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_stream_data_bidi_remote(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_stream_data_uni(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_streams_bidi(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_streams_uni(quiche_config *config, uint64_t v);
int quiche_config_set_cc_algorithm_name(quiche_config *config, const char *algo);
void quiche_config_enable_dgram(quiche_config *config, bool enabled, size_t recv_queue_len, size_t send_queue_len);

quiche_conn *quiche_connect(const char *server_name, const uint8_t *scid, size_t scid_len, const struct sockaddr *local, socklen_t local_len, const struct sockaddr *peer, socklen_t peer_len, quiche_config *config);
bool quiche_conn_set_keylog_path(quiche_conn *conn, const char *path);
ssize_t quiche_conn_recv(quiche_conn *conn, uint8_t *buf, size_t buf_len, const quiche_recv_info *info);
ssize_t quiche_conn_send(quiche_conn *conn, uint8_t *out, size_t out_len, quiche_send_info *out_info);
uint64_t quiche_conn_timeout_as_millis(const quiche_conn *conn);
void quiche_conn_on_timeout(quiche_conn *conn);
bool quiche_conn_is_established(const quiche_conn *conn);
bool quiche_conn_is_closed(const quiche_conn *conn);
bool quiche_conn_peer_error(const quiche_conn *conn, bool *is_app, uint64_t *error_code, const uint8_t **reason, size_t *reason_len);
bool quiche_conn_local_error(const quiche_conn *conn, bool *is_app, uint64_t *error_code, const uint8_t **reason, size_t *reason_len);
void quiche_conn_application_proto(const quiche_conn *conn, const uint8_t **out, size_t *out_len);
int quiche_conn_close(quiche_conn *conn, bool app, uint64_t err, const uint8_t *reason, size_t reason_len);

ssize_t quiche_conn_stream_send(quiche_conn *conn, uint64_t stream_id, const uint8_t *buf, size_t buf_len, bool fin, uint64_t *out_error_code);
ssize_t quiche_conn_stream_recv(quiche_conn *conn, uint64_t stream_id, uint8_t *out, size_t buf_len, bool *fin, uint64_t *out_error_code);
bool quiche_conn_stream_finished(const quiche_conn *conn, uint64_t stream_id);
quiche_stream_iter *quiche_conn_readable(const quiche_conn *conn);
quiche_stream_iter *quiche_conn_writable(const quiche_conn *conn);
bool quiche_stream_iter_next(quiche_stream_iter *iter, uint64_t *stream_id);
void quiche_stream_iter_free(quiche_stream_iter *iter);
void quiche_conn_free(quiche_conn *conn);
CDEF;
    }
}
