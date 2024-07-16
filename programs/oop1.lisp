(def ctor (lambda (a)
    (do
  (def inc (lambda (x) (do (set! a (+ a x)) a)))
  (def dec (lambda (x) (do (set! a (- a x)) a)))
  (def get (lambda ()      a))
  (lambda (msg)
    (cond (= (quote inc) msg) inc
      (cond (= (quote dec) msg) dec
        (cond (= (quote get) msg) get "?")))))))