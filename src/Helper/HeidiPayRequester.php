<?php

namespace Go2FlowHeidiPayPayment\Helper;

class HeidiPayRequester {

    /** @var string */
    private string $url = '';

    /** @var string[] */
    private array $headers = array(
        'Content-Type'              => 'application/json',
        'UserAgent'                 => 'shopware-6',
        'X-Client-Platform'         => 'shopware-6',
        'X-Client-Platform-Version' => '1.0.0',
        'X-Client-Module'           => 'heidi-shopware-6',
        'X-Client-Module-Version'   => '1.0.0',
    );

    /**
     * @param string $url
     */
    public function setUrl(string $url ): void
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addHeader(string $key, string $value ): void
    {
        $this->headers[ $key ] = $value;
    }

    /**
     * @param string[] $headers
     */
    public function setHeaders( array $headers ): void
    {
        $this->headers = $headers;
    }

    /**
     * @param array $body
     * @return array
     */
    public function post(array $body ): array
    {
        return $this->request( true, $body );
    }

    /**
     * @return array
     */
    public function get(): array
    {
        return $this->request();
    }

    /**
     * @param bool $methodPost
     * @param array $body
     * @return array
     */
    public function request(bool $methodPost = false, array $body = array() ): array
    {
        $headers = array();
        foreach ( $this->headers as $key => $value ) {
            $headers[] = $key . ':' . $value;
        }

        $ch = curl_init( $this->url );
        curl_setopt( $ch, CURLOPT_POST, $methodPost );
        if ( $methodPost ) {
            curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $body ) );
        }
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        $contents = curl_exec( $ch );
        $code     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        return compact( 'contents', 'code' );
    }
}
