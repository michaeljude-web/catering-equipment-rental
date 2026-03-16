<?php
class Pagination {
    private $total;
    private $limit;
    private $page;

    public function __construct($total, $page, $limit) {
        $this->total = $total;
        $this->limit = $limit;
        $this->page = $page;
    }

    public function totalPages() {
        return ceil($this->total / $this->limit);
    }

    public function currentPage() {
        return $this->page;
    }

    public function getOffset() {
        return ($this->page - 1) * $this->limit;
    }

    public function render($ulClasses = 'pagination-sm mb-0', $baseUrl = '?page=') {
        $total_pages = $this->totalPages();
        if ($total_pages <= 1) return '';

        $html = '<nav><ul class="pagination '.$ulClasses.'">';

        $prev = max(1, $this->page - 1);
        $disabled = ($this->page <= 1) ? ' disabled' : '';
        $html .= '<li class="page-item'.$disabled.'">
                    <a class="page-link" href="'.$baseUrl.$prev.'"><i class="fas fa-angle-left"></i></a>
                  </li>';

        $range = 2;
        $start = max(1, $this->page - $range);
        $end = min($total_pages, $this->page + $range);

        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="'.$baseUrl.'1">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = ($i == $this->page) ? ' active' : '';
            $html .= '<li class="page-item'.$active.'">
                        <a class="page-link" href="'.$baseUrl.$i.'">'.$i.'</a>
                      </li>';
        }

        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="'.$baseUrl.$total_pages.'">'.$total_pages.'</a></li>';
        }

        $next = min($total_pages, $this->page + 1);
        $disabled = ($this->page >= $total_pages) ? ' disabled' : '';
        $html .= '<li class="page-item'.$disabled.'">
                    <a class="page-link" href="'.$baseUrl.$next.'"><i class="fas fa-angle-right"></i></a>
                  </li>';

        $html .= '</ul></nav>';
        return $html;
    }
}
